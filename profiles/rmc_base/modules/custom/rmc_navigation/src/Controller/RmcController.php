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
    $current_route = \Drupal::service('current_route_match')->getRouteName();
    $render = [
      '#type' => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
    if ('rmc_navigation.dashboard' === $current_route) {
        $render['#markup'] = self::dashboard();
        // $render['#attached']['library'][] = 'rmc_navigation/rmc_navigation';
    }
    return $render;
  }

  /**
   * Returns the page title for local tasks.
   *
   * @return string
   */
  public function title() {
    return \Drupal::service('current_route_match')->getParameter('taxonomy_term')->getName();
  }

  /**
   * Display the markup.
   *
   * @return array
   */
  public static function dashboard($group_type = 'kfp_affiliation') {
    if (in_array($group_type, ['kfp_fund', 'kfp_affiliation'])) {
      $proposal_panel = <<<HTML
<div class="masonry-layout__panel">
  <div class="panel">
    <h3 class="panel__title">Proposals in Committee</h3>
    <table class="views-table views-view-table cols-3 responsive-enabled panel__content">
      <thead>
        <tr>
          <th id="view-title-table-column" class="views-field views-field-title" scope="col"><a href="#" title="sort by Name">Application</a></th>
          <th id="view-type-table-column" class="views-field views-field-type" scope="col"><a href="#" title="sort by Amount Requested">Requested</a></th>
          <th id="view-status-table-column" class="views-field views-field-status" scope="col"><a href="#" title="sort by Time Left to Vote">Time Left
            <span class="tablesort tablesort--asc">
              <span class="visually-hidden">Sort ascending</span>
            </span>
          </a></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td headers="view-title-table-column" class="views-field views-field-title">
            <a href="proposals" hreflang="und">Supporting MAGNET's Early College, Early Career Program</a>
          </td>
          <td headers="view-type-table-column" class="views-field views-field-type">
            $30,000
          </td>
          <td headers="view-status-table-column" class="views-field views-field-status">
            3 days
          </td>
        </tr>
        <tr>
          <td headers="view-title-table-column" class="views-field views-field-title">
            <a href="proposals" hreflang="und">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy</a>
          </td>
          <td headers="view-type-table-column" class="views-field views-field-type">
            $30,000
          </td>
          <td headers="view-status-table-column" class="views-field views-field-status">
            3 days
          </td>
        </tr>
        <tr>
          <td headers="view-title-table-column" class="views-field views-field-title">
            <a href="proposals" hreflang="und">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy</a>
          </td>
          <td headers="view-type-table-column" class="views-field views-field-type">
            $30,000
          </td>
          <td headers="view-status-table-column" class="views-field views-field-status">
            3 days
          </td>
        </tr>
        <tr>
          <td headers="view-title-table-column" class="views-field views-field-title">
            <a href="proposals" hreflang="und">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy</a>
          </td>
          <td headers="view-type-table-column" class="views-field views-field-type">
            $30,000
          </td>
          <td headers="view-status-table-column" class="views-field views-field-status">
            3 days
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
HTML;
    }

    $slack_panel = <<<HTML
<div class="masonry-layout__panel">
  <div class="panel">
    <h3 class="panel__title">Recent Slack Messages</h3>
    <div class="panel__content">
      <h4 class="date">Today</h4>
      <ul class="admin-list">
        <li>
          <a href="#">
            <span class="time time-12hour">10:08 <span>am</span></span>
            <span class="label">Susan Kettering</span>
            <div class="description">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy nibh euismod ticidunt ut laoreet dolore magna aliquam erat volut</div>
          </a>
        </li>
      </ul>
    </div>
    <div class="panel__content">
      <h4 class="date">Yesterday</h4>
      <ul class="admin-list">
        <li>
          <a href="#">
            <span class="time time-12hour">5:32 <span>pm</span></span>
            <span class="label">Charlie Kettering</span>
            <div class="description">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy nibh euismod ticidunt ut laoreet dolore magna aliquam erat volut</div>
          </a>
        </li>
        <li>
          <a href="#">
            <span class="time time-12hour">3:21 <span>pm</span></span>
            <span class="label">Grant Kettering</span>
            <div class="description">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed diam nonummy nibh euismod ticidunt ut laoreet dolore magna aliquam erat volut</div>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
HTML;

    $meeting_panel = <<<HTML
<div class="masonry-layout__panel">
  <div class="panel">
    <h3 class="panel__title">Upcoming Meetings</h3>
    <div class="panel__content">
      <h4 class="date">2017</h4>
      <table class="views-table views-view-table cols-3 responsive-enabled panel__content">
        <tbody>
          <tr>
            <td headers="view-title-table-column" class="views-field views-field-title">
              Dec 28 &mdash; Jan 03
            </td>
            <td headers="view-status-table-column" class="views-field views-field-status">
              <a href="meetings" hreflang="und">KFIP &amp; KFF IC Meetings - Denver, CO</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="panel__content">
      <h4 class="date">2018</h4>
      <table class="views-table views-view-table cols-2 responsive-enabled panel__content">
        <tbody>
          <tr>
            <td headers="view-title-table-column" class="views-field views-field-title">
              Mar 12
            </td>
            <td headers="view-status-table-column" class="views-field views-field-status">
              <a href="meetings" hreflang="und">KFIP &amp; KFF IC Meetings - Denver, CO</a>
            </td>
          </tr>
          <tr>
            <td headers="view-title-table-column" class="views-field views-field-title">
              Jun 27 &mdash; Jun 30
            </td>
            <td headers="view-status-table-column" class="views-field views-field-status">
              <a href="meetings" hreflang="und">KFIP &amp; KFF IC Meetings - Denver, CO</a>
            </td>
          </tr>
          <tr>
            <td headers="view-title-table-column" class="views-field views-field-title">
              Aug 12 &mdash; Aug 17
            </td>
            <td headers="view-status-table-column" class="views-field views-field-status">
              <a href="meetings" hreflang="und">KFIP &amp; KFF IC Meetings - Denver, CO</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
HTML;

    $document_panel = <<<HTML
<div class="masonry-layout__panel">
  <div class="panel">
    <h3 class="panel__title">Recent Documents</h3>
    <div class="panel__content">
      <h4 class="date">Today</h4>
      <div class="js-form-managed-file form-managed-file">
        <span data-drupal-selector="edit-field-temp-0-file-1-filename" class="file file--mime-text-plain file--text">
          <a href="documents" type="text/plain; length=1657">Lorem ipsum dolor sit amet, consectetur adipiscing</a>
        </span>
      </div>
      <div class="js-form-managed-file form-managed-file">
        <span data-drupal-selector="edit-field-temp-0-file-1-filename" class="file file--mime-text-plain file--text">
          <a href="documents" type="text/plain; length=1657">Lorem ipsum dolor sit amet, consectetur adipiscing</a>
        </span>
      </div>
      <div class="js-form-managed-file form-managed-file">
        <span data-drupal-selector="edit-field-temp-0-file-1-filename" class="file file--mime-text-plain file--text">
          <a href="documents" type="text/plain; length=1657">Lorem ipsum dolor sit amet, consectetur adipiscing</a>
        </span>
      </div>
      <div class="js-form-managed-file form-managed-file">
        <span data-drupal-selector="edit-field-temp-0-file-1-filename" class="file file--mime-text-plain file--text">
          <a href="documents" type="text/plain; length=1657">Lorem ipsum dolor sit amet, consectetur adipiscing</a>
        </span>
      </div>
      <div class="js-form-managed-file form-managed-file">
        <span data-drupal-selector="edit-field-temp-0-file-1-filename" class="file file--mime-text-plain file--text">
          <a href="documents" type="text/plain; length=1657">Lorem ipsum dolor sit amet, consectetur adipiscing</a>
        </span>
      </div>
    </div>
  </div>
</div>
HTML;

    return <<<HTML
<div class="masonry-layout rmc-dashboard-content">
  <div class="masonry-layout__column">
    $proposal_panel
    $slack_panel
  </div>
  <div class="masonry-layout__column">
    $meeting_panel
    $document_panel
  </div>
</div>
HTML;
  }
}
