<?php
/**
 * Null implementation for path.inc
 */

function drupal_path_initialize() {}

function drupal_lookup_path($action, $path = '', $path_language = NULL) {
  if ($action === 'alias') {
    return $path;
  }
  return false;
}

function drupal_cache_system_paths() {}

function drupal_get_path_alias($path = NULL, $path_language = NULL) {
  return drupal_lookup_path('alias', $path, $path_language);
}

function drupal_get_normal_path($path, $path_language = NULL) {
  return drupal_lookup_path('source', $path, $path_language);
}

function drupal_is_front_page() {
  return false;
}

function drupal_match_path($path, $patterns) {
  $regexps = &drupal_static(__FUNCTION__);
  if (!isset($regexps[$patterns])) {
    // Convert path settings to a regular expression.
    // Therefore replace newlines with a logical or, /* with asterisks and the <front> with the frontpage.
    $to_replace = array(
        '/(\r\n?|\n)/', // newlines
        '/\\\\\*/',     // asterisks
        '/(^|\|)\\\\<front\\\\>($|\|)/' // <front>
    );
    $replacements = array(
        '|',
        '.*',
        '\1' . preg_quote(variable_get('site_frontpage', 'node'), '/') . '\2'
    );
    $patterns_quoted = preg_quote($patterns, '/');
    $regexps[$patterns] = '/^(' . preg_replace($to_replace, $replacements, $patterns_quoted) . ')$/';
  }
  return (bool)preg_match($regexps[$patterns], $path);
}

function current_path() {
  return 'install';
}

function drupal_path_alias_whitelist_rebuild($source = NULL) {}

function path_load($conditions) {}

function path_save(&$path) {}

function path_delete($criteria) {}

function path_is_admin($path) {
  $path_map = &drupal_static(__FUNCTION__);
  if (!isset($path_map['admin'][$path])) {
    $patterns = path_get_admin_paths();
    $path_map['admin'][$path] = drupal_match_path($path, $patterns['admin']);
    $path_map['non_admin'][$path] = drupal_match_path($path, $patterns['non_admin']);
  }
  return $path_map['admin'][$path] && !$path_map['non_admin'][$path];
}

function path_get_admin_paths() {
  $patterns = &drupal_static(__FUNCTION__);
  if (!isset($patterns)) {
    $paths = module_invoke_all('admin_paths');
    drupal_alter('admin_paths', $paths);
    // Combine all admin paths into one array, and likewise for non-admin paths,
    // for easier handling.
    $patterns = array();
    $patterns['admin'] = array();
    $patterns['non_admin'] = array();
    foreach ($paths as $path => $enabled) {
      if ($enabled) {
        $patterns['admin'][] = $path;
      }
      else {
        $patterns['non_admin'][] = $path;
      }
    }
    $patterns['admin'] = implode("\n", $patterns['admin']);
    $patterns['non_admin'] = implode("\n", $patterns['non_admin']);
  }
  return $patterns;
}

function drupal_valid_path($path, $dynamic_allowed = FALSE) {
  global $menu_admin;
  // We indicate that a menu administrator is running the menu access check.
  $menu_admin = TRUE;
  if ($path == '<front>' || url_is_external($path)) {
    $item = array('access' => TRUE);
  }
  elseif ($dynamic_allowed && preg_match('/\/\%/', $path)) {
    // Path is dynamic (ie 'user/%'), so check directly against menu_router table.
    if ($item = db_query("SELECT * FROM {menu_router} where path = :path", array(':path' => $path))->fetchAssoc()) {
      $item['link_path']  = $item['path'];
      $item['link_title'] = $item['title'];
      $item['external']   = FALSE;
      $item['options'] = '';
      _menu_link_translate($item);
    }
  }
  else {
    $item = menu_get_item($path);
  }
  $menu_admin = FALSE;
  return $item && $item['access'];
}

function drupal_clear_path_cache($source = NULL) {}