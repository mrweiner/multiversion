<?php

namespace Drupal\multiversion\Workspace;

use Drupal\multiversion\Entity\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

class DefaultWorkspaceNegotiator implements WorkspaceNegotiatorInterface {

  use ContainerAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspaceId(Request $request) {
    return $this->container->getParameter('workspace.default');
  }

  /**
   * {@inheritdoc}
   */
  public function persist(WorkspaceInterface $workspace) {
    return TRUE;
  }

}
