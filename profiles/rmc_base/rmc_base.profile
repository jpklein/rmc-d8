<?php

/**
 * @file
 * Enables modules and site configuration for a site installation.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function rmc_base_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  $form['site_information']['site_name']['#default_value'] = 'RMC Testbed';
  $form['site_information']['site_mail']['#default_value'] = 'phlip@yahoo.com';
  $form['admin_account']['account']['name']['#default_value'] = 'admin';
  $form['admin_account']['account']['mail']['#default_value'] = 'phlip@yahoo.com';
  // $form['admin_account']['account']['pass']['#default_value'] = 'password';
  // $form['update_notifications']['update_status_module']['#default_value'] = array(1);
}

/**
 * Returns an array of tasks to be performed by an installation profile.
 */
function rmc_base_install_tasks(&$install_state) {
  return array(
    'rmc_base_additional_modules_task' => array(
      'display_name' => t('Install additional modules'),
      'display' => TRUE,
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    ),
    'rmc_base_post_installation_task' => array(),
  );
}

function rmc_base_additional_modules_task() {
  return [
    'operations' => [
      [ '_install_module_batch', [ 'gnode', 'Groups Node' ] ],
      [ '_install_module_batch', [ 'ggroup', 'Subgroups' ] ],
      [ '_install_module_batch', [ 'migrate_drupal_ui', 'Migrate Drupal UI' ] ],
    ],
    'title' => 'Installing additional modules',
    'error_message' => t('The installation has encountered an error.'),
  ];
}

function rmc_base_post_installation_task(&$install_state) {
  // node_access_rebuild(TRUE);
  file_unmanaged_save_data('{}', 'private://secrets.json', FILE_EXISTS_ERROR);
  $query = array('continue' => 1);
  return array(
    '#title' => 'Prepare migration',
    '#markup' => 'copy/paste the following to your local terminal',
    'codeblock0' => array(
      '#type' => 'textarea',
      '#title' => '[optional] reestablish a terminus session',
      '#value' => ''
        ."terminus auth:login --email=jpklein@gmail.com;\n"
        .'',
      '#rows' => 1,
    ),
    'codeblock1' => array(
      '#type' => 'textarea',
      '#title' => '[optional] wake the source environment',
      '#value' => ''
        ."terminus env:wake rmc-migrate-source.dev;\n"
        .'',
      '#rows' => 1,
    ),
    'codeblock2' => array(
      '#type' => 'textarea',
      '#title' => 'set up a connection to the source db',
      '#value' => ''
        ."export D7_MYSQL_URL=\$(terminus connection:info --field=mysql_url rmc-migrate-source.dev);\n"
        ."terminus secrets:set rmc-testbed.dev migrate_source_db__url \$D7_MYSQL_URL;\n"
        .'',
      '#rows' => 4,
    ),
    'codeblock3' => array(
      '#type' => 'textarea',
      '#title' => '[optional] execute rmc migrations',
      '#value' => ''
        ."terminus remote:drush rmc-testbed.dev -- migrate-import --group=rmc -vvv;\n"
        .'',
      '#rows' => 2,
    ),
    'codeblock4' => array(
      '#type' => 'textarea',
      '#title' => '[optional] generate default migrations',
      '#value' => ''
        ."terminus remote:drush rmc-testbed.dev -- migrate-upgrade --legacy-db-key=migrate --configure-only -vvv;\n"
        .'',
      '#rows' => 2,
    ),
    'nextstep' => array(
      '#markup' => t('<hr>Click to <a href=":cont">continue</a> when done', array(':cont' => drupal_current_script_url($query))),
    ),
  );
}
