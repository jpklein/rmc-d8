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
