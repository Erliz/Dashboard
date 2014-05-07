<?php

namespace Erliz\Dashboard\Controller;

use Erliz\Dashboard\Service\FlashBagService;
use Erliz\Dashboard\Service\JiraService;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AgileController.
 *
 * @author Stanislav Vetlovskiy <s.vetlovskiy@corp.mail.ru>
 */ 
class AgileController
{
    const RELEASE_PROJECT_KEY = 'MNT';
    const RELEASE_LABEL = 'merged';
    const DEV_LABEL = 'in_branch_dev';

    public function indexAction(Request $request, Application $app)
    {
        return $app['twig']->render(
            'Agile/index.twig',
            array()
        );
    }

    public function issueAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $issue = $jiraService->getIssue($request->get('key'));
        return $app['twig']->render(
            'Agile/issue.twig',
            array(
                'issue' => $issue,
                'release_label' => $this::RELEASE_LABEL,
                'dev_label' => $this::DEV_LABEL
            )
        );
    }

    public function issueAddLabelAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $issue = $jiraService->getIssue($request->get('key'));
        $jiraService->addLabel($issue, $request->get('label'));

        return $app->redirect($app['url_generator']->generate('agile_issue', array('key' => $issue->getKey())));
    }

    public function issueRemoveLabelAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $issue = $jiraService->getIssue($request->get('key'));
        $jiraService->removeLabel($issue, $request->get('label'));

        return $app->redirect($app['url_generator']->generate('agile_issue', array('key' => $issue->getKey())));
    }

    public function issueTestCommentAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $issue = $jiraService->getIssue($request->get('key'));
        $jiraService->addTestComment($issue);

        return $app->redirect($app['url_generator']->generate('agile_issue', array('key' => $issue->getKey())));
    }

    public function issueTransitionAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $issue = $jiraService->getIssue($request->get('key'));

        $jiraService->transitIssue($issue, $request->get('id'));

        return $app->redirect($app['url_generator']->generate('agile_issue', array('key' => $issue->getKey())));
    }

    public function newReleaseAction(Request $request, Application $app)
    {
        $key = $request->get('key');
        if(!empty($key)) {
            return new RedirectResponse($app['url_generator']->generate(
                'agile_release',
                array('key' => sprintf('%s-%d', $this::RELEASE_PROJECT_KEY, $request->get('key')))
            ));
        }
        
        return $app['twig']->render(
            'Agile/ReleaseManager/new.twig',
            array('release_project' => $this::RELEASE_PROJECT_KEY)
        );
    }

    public function releaseAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $key = $request->get('key');
        $issue = $jiraService->getIssue($key);

        return $app['twig']->render(
            'Agile/ReleaseManager/index.twig',
            array(
                'release_issue' => $issue,
                'release_label' => $this::RELEASE_LABEL,
                'release_tags' => $jiraService->getVersionsFromString($issue->getSummary()),
                'linked_issues_by_projects' => $jiraService->IssuesInLinksByProject($issue->getLinks()),
                'flash_bag' => $app['service.flash_bag']->getMessages()
            )
        );
    }

    public function releaseLabelRemoveAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $key = $request->get('key');
        $issue = $jiraService->getIssue($key);

        $jiraService->removeLabelFromLinks($issue, $this::RELEASE_LABEL);
        $app['service.flash_bag']->success(
            sprintf('Successfully remove label "%s" from linked issues', $this::RELEASE_LABEL)
        );

        return $app->redirect($app['url_generator']->generate('agile_release', array('key' => $key)));
    }

    public function releaseTransitionAction(Request $request, Application $app)
    {
        /** @var JiraService $jiraService */
        $jiraService = $app['service.jira'];
        $key = $request->get('key');
        $issue = $jiraService->getIssue($key);

        $result = $jiraService->transitIssuesFromLinks(
            $issue,
            array(
                $app['config']['jira']['transition']['torg']['check'],
                $app['config']['jira']['transition']['frontend']['check']
            )
        );

        /** @var FlashBagService $flasBag */
        $flasBag = $app['service.flash_bag'];
        if ($result['success']) {
            $flasBag->success(sprintf('Successfully transit %d from %d issues', $result['success'], count($issue->getLinks())));
        }
        if ($result['fail']) {
            $flasBag->error('Fail to transit %d from %d issues', $result['fail'], count($issue->getLinks()));
        }
        if (!empty($result['fails'])) {
            foreach ($result['fails'] as $fail) {
                foreach ($fail['items'] as $item) {
                    $flasBag->error(sprintf('%s "%s"', $fail['message'], $item->getKey()));
                }
            }
        }

        return $app->redirect($app['url_generator']->generate('agile_release', array('key' => $key)));
    }
}
