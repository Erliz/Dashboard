<?php

namespace Erliz\Dashboard\Controller;

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
            array('issue' => $issue)
        );
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
                'linked_issues_by_projects' => $jiraService->IssuesInLinksByProject($issue->getLinks())
            )
        );
    }
}
