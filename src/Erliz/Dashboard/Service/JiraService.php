<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   09.04.14
 */

namespace Erliz\Dashboard\Service;

use Erliz\JiraApiClient\Api\Client as ApiClient;
use Erliz\JiraApiClient\Entity\Issue;
use Erliz\JiraApiClient\Entity\IssueLink;
use Erliz\JiraApiClient\Http\Client as HttpClient;
use Erliz\JiraApiClient\Manager\EntityManager;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class JiraService
{
    /** @var ApiClient */
    private $apiClient;

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
}
