<?php

/**
 * @file
 * Configuration file for Drupal's multi-site directory aliasing feature.
 */

if (!function_exists('acsf_hooks_includes')) {

  /**
   * Scans a factory-hooks sub-directory and returns PHP files to be included.
   *
   * @param string $hook_name
   *   The name of the hook whose files should be returned.
   *
   * @return string[]
   *   A list of customer-defined hook files to include sorted alphabetically
   *   ascending.
   */
  function acsf_hooks_includes($hook_name) {
    $hook_pattern = sprintf('%s/../factory-hooks/%s/*.php', getcwd(), $hook_name);
    return glob($hook_pattern);
  }

}

// Include custom sites.php code from factory-hooks/pre-sites-php.
foreach (acsf_hooks_includes('pre-sites-php') as $pre_hook) {
  include $pre_hook;
}

if (!function_exists('is_acquia_host')) {

  /**
   * Checks whether the site is on Acquia Hosting.
   *
   * @return bool
   *   TRUE if the site is on Acquia Hosting, otherwise FALSE.
   */
  function is_acquia_host() {
    return file_exists('/var/acquia');
  }

}

// Check that we are on an Acquia server so we do not run this code for local
// development.
if (!is_acquia_host()) {
  return;
}

// There are some drush commands which run other commands as a post execution
// task, for example the drush updb which automatically executes a cache clear
// or rebuild after the update has finished, however this is handled by invoking
// the relevant drush command in a different process on the same site. Since we
// are calling these drush commands without an alias, drush8 is trying to
// discover if there is an alias that covers the current site, and in the
// process it walks over the drush aliases file and includes the sites.php for
// each entry. In our case drush will include the sites.php for the live and the
// update environment causing a fatal php error because sites.php includes
// sites.inc and the functions would be redefined on the fly.
// When calling the commands with an alias a different issue surfaces: starting
// from drush7, drush is static caching the alias entries as is, meaning that
// the extra root and uri parameters we pass to the drush command do not get
// applied to the static alias entry and when drush is trying to run the cache
// clear or rebuild using this static cache then it is going to try to run it on
// the wrong site.
// Therefore, for the time being, safeguard the sites.inc inclusion and avoid
// using aliases.
if (!function_exists('gardens_site_data_load_file')) {
  require_once dirname(__FILE__) . '/g/sites.inc';
}

// Prevents to run further if the sites.json file doesn't exists.
// This step also tries to prevent errors on a none acsf environment.
if (empty($_ENV['AH_SITE_GROUP']) || empty($_ENV['AH_SITE_ENVIRONMENT']) || !function_exists('gardens_site_data_get_filepath') || !file_exists(gardens_site_data_get_filepath())) {
  return;
}

$_tmp = gardens_site_data_get_site_from_server_info();

// If either "not found" or "read failure" (from either the cache or the
// sites.json file): don't set $sites and fall through (to, probably, reading
// sites/default/settings.php for settings).
if (empty($_tmp)) {
  if ($_tmp === NULL) {
    // If we encountered a read error, indicate that we want the same (short)
    // cache time for the page, as we have for the data in APC.
    $GLOBALS['gardens_site_settings']['page_ttl'] = GARDENS_SITE_DATA_READ_FAILURE_TTL;
  }
  return;
}

// We found a site, so add the corresponding 'configuration directory' to
// $sites, as per the regular sites.php spec. (For most Drupal sites this is
// a single-layer directory equal to a domain name; for us, it is typically
// g/files/SITE-ID.)
$sites[$_tmp['dir_key']] = $_tmp['dir'];
// Also set 'gardens_site_settings' for other code further on. (Mainly
// settings.php.)
$GLOBALS['gardens_site_settings'] = $_tmp['gardens_site_settings'];

// Include custom sites.php code from factory-hooks/post-sites-php, only when
// a domain was found.
foreach (acsf_hooks_includes('post-sites-php') as $post_hook) {
  include $post_hook;
}
