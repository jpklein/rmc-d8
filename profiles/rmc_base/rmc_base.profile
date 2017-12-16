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
    ],
    'title' => 'Installing additional modules',
    'error_message' => t('The installation has encountered an error.'),
  ];
}

function rmc_base_post_installation_task() {
  node_access_rebuild(TRUE);
}
