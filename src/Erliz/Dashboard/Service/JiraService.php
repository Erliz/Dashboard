<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   09.04.14
 */

namespace Erliz\Dashboard\Service;

use Erliz\JiraApiClient\Api\Client as ApiClient;
use Erliz\JiraApiClient\Entity\Comment;
use Erliz\JiraApiClient\Entity\Issue;
use Erliz\JiraApiClient\Entity\IssueLink;
use Erliz\JiraApiClient\Entity\Transition;
use Erliz\JiraApiClient\Http\Client as HttpClient;
use Erliz\JiraApiClient\Manager\EntityManager;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class JiraService
{
    /** @var ApiClient */
    private $apiClient;
    /** @var array */
    private $issueTestbed;

    /**
     * @param string $login
     * @param string $password
     * @param string $host
     * @param bool   $isSSL
     */
    public function __construct($login, $password, $host, $isSSL = true)
    {
        $this->apiClient = new ApiClient($host, $isSSL);
        $this->apiClient->setHttpClient(new HttpClient());
        $this->apiClient->setCredentials($login, $password);
        $this->apiClient->setEntityManager(new EntityManager());
    }

    /**
     * @param string $host
     * @param string $scheme
     */
    public function setTestbed($host, $scheme = 'http')
    {
        $this->issueTestbed = array(
            'host' => $host,
            'scheme' => $scheme
        );
    }

    /**
     * @param $key
     *
     * @throws \Exception
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     * @throws \Symfony\Component\Security\Core\Exception\BadCredentialsException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return Issue
     */
    public function getIssue($key)
    {
        try {
            $issue = $this->apiClient->getIssue($key);
        } catch (ClientErrorResponseException $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            if ($httpCode == 404) {
                throw new NotFoundHttpException("No Issue with key '$key'", $e);
            } elseif ($httpCode == 403) {
                throw new BadCredentialsException("Bad login or password", 0, $e);
            } else {
                throw $e;
            }
        }

        return $issue;
    }

    public function getLinkedIssueFor(Issue $issue)
    {
        $issues = $issue->getLinks();
        $result = array();
        foreach($issues as $key => $linkedIssue){
            $result[] = $this->getIssue($key);
        }

        return $result;
    }

    /**
     * Contains duplicate counts
     * @param IssueLink[] $issueLinks
     *
     * @return array
     */
    public function IssuesInLinksByProject(array $issueLinks)
    {
        $projects = array();

        foreach($issueLinks as $link) {
            $projectKey = Issue::getProjectKeyFromKey($link->getIssue()->getKey());
            if(!isset($projects[$projectKey])) {
                $projects[$projectKey] = array();
            }
            $projects[$projectKey][] = $link->getIssue();
        }

        return $projects;
    }

    /**
     * @param $string
     *
     * @return bool
     */
    public function getVersionsFromString($string)
    {
        preg_match_all('/v?(\d+\.\d+\.\d+)/', $string, $match);

        return isset($match[1]) ? $match[1] : false;
    }

    /**
     * @param Issue  $issue
     * @param string $label
     */
    public function removeLabelFromLinks(Issue $issue, $label)
    {
        foreach($issue->getLinks() as $link) {
            $this->removeLabel($link->getIssue(), $label);
        }
    }

    /**
     * @param Issue  $issue
     * @param string $label
     */
    public function addLabel(Issue $issue, $label)
    {
        $issue->addLabel($label);
        $this->apiClient->updateIssueData($issue->getKey(), array('fields'=>array('labels' => $issue->getLabels())));
    }

    /**
     * @param Issue  $issue
     * @param string $label
     */
    public function removeLabel(Issue $issue, $label)
    {
        $labels = $issue->getLabels();
        if(($key = array_search($label, $labels)) !== false) {
            unset($labels[$key]);
            $issue->setLabels($labels);
            $this->apiClient->updateIssueData($issue->getKey(), array('fields'=>array('labels' => $issue->getLabels())));
        }
    }

    public function addTestComment(Issue $issue)
    {
        $comment = new Comment();
        $comment->setBody("Тестовый стенд разложен и доступен по адресу: \n" . $this->generateTestbedUrl($issue));
        $this->addComment($issue, $comment);
        $issue->addComment($comment);
    }

    public function addComment(Issue $issue, Comment $comment)
    {
        $this->apiClient->addCommentData($issue->getKey(), array('body' => $comment->getBody()));
    }

    /**
     * @param Issue $issue
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    private function generateTestbedUrl(Issue $issue)
    {
        if (empty($this->issueTestbed)) {
            throw new \RuntimeException('Not set testbed host and scheme');
        }

        return sprintf(
            '%s://%s.%s',
            $this->issueTestbed['scheme'],
            strtolower($issue->getKey()),
            $this->issueTestbed['host']
        );
    }

    /**
     * @param Issue $issue
     * @param int   $transitionId
     *
     * @throws \RuntimeException
     */
    public function transitIssue(Issue $issue, $transitionId)
    {
        $transition = $issue->getTransition($transitionId);
        if (!$transition) {
            throw new \RuntimeException(
                sprintf("Transition id '%s' not available for issue '%s'", $transitionId, $issue->getKey())
            );
        }

        $this->apiClient->transitIssue($issue->getKey(), array('transition' => array('id' => $transition->getId())));
    }

    /**
     * @param Issue     $issue
     * @param int|int[] $transitionsId available transitions code
     *
     * @return array
     */
    public function transitIssuesFromLinks(Issue $issue, $transitionsId)
    {
        $result = array(
            'success' => 0,
            'fail' => 0,
            'fails' => array(
                array('message' => 'Issue have no available transition', 'items' => array()),
                array('message' => 'Fail on transaction', 'items' => array())
            )
        );
        if (!is_array($transitionsId)) {
            $transitionsId = array($transitionsId);
        }

        foreach($issue->getLinks() as $link) {
            $logged = false;
            try {
                foreach($transitionsId as $id) {
                    if ($transition = $link->getIssue()->getTransition($id)) {
                        $this->transitIssue($link->getIssue(), $transition->getId());
                        $result['success']++;
                        $logged =  true;
                        continue(2);
                    }
                }
            } catch (\RuntimeException $e) {
                $result['fails'][1]['items'][] = $link->getIssue();
                $result['fail']++;
                $logged = true;
            }
            if (!$logged) {
                $result['fails'][0]['items'][] = $link->getIssue();
                $result['fail']++;
            }
        }

        return $result;
    }
}
