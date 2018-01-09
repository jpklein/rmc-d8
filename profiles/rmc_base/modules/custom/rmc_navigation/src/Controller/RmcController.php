<?php

namespace Drupal\rmc_navigation\Controller;


use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

class RmcController extends ControllerBase {

  /**
   * Checks access for a specific request.
   *
   * @return boolean
   */
  public function access() {
    $group_type = \Drupal::service('current_route_match')->getParameter('taxonomy_term')->getDescription();

    return AccessResult::allowedIf(in_array($group_type, ['fund', 'kfp_affiliation', 'kfp_fund']));
  }

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
  }

  /**
   * Returns the page title for local tasks.
   *
   * @return string
   */
  public function title() {
    return \Drupal::service('current_route_match')->getParameter('taxonomy_term')->getName();
  }
}
