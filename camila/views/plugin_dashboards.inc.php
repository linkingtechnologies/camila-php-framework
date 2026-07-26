<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2025 Umberto Bresciani

    Camila PHP Framework is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Camila PHP Framework is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Camila PHP Framework. If not, see <http://www.gnu.org/licenses/>. */

defined('CAMILA_APPLICATION_NAME') or die('No direct script access.');

/**
 * Shared "dashboard" tab dispatcher for app plugins.
 *
 * Every plugin's own plugins/<id>/dashboards.inc.php should just do:
 *
 *   <?php
 *   $_camilaPluginDir = __DIR__;
 *   require_once(CAMILA_DIR . '/views/plugin_dashboards.inc.php');
 *
 * This file then:
 *  - suppresses export and the tab bar for totem/kiosk users (username starting with "totem")
 *  - prints the tab bar from that plugin's own conf/menu.xml
 *  - resolves the "dashboard" request param and require()s the matching dashboard-<id>.inc.php
 *  - with no "dashboard" param, defaults to the id of the first <tab> in that
 *    plugin's own conf/menu.xml (falling back to "m0" if that can't be read)
 *
 * Dashboard id namespacing
 * -------------------------
 * A bare id (e.g. "mcp") targets the calling plugin's own dashboard-mcp.inc.php.
 * A namespaced id "<plugin>--<dashboard>" (e.g. "travel-planner--itinerary-map")
 * targets another plugin's dashboard-<dashboard>.inc.php instead, so any plugin
 * can link into any other plugin's dashboards. The separator is "--" (not a
 * single "-") because plugin and dashboard ids themselves may contain single
 * dashes (e.g. "worktable-explorer").
 *
 * To link to another plugin's *home* tab without needing to know its id, leave
 * the dashboard part empty: "<plugin>--" (e.g. "travel-planner--") resolves to
 * that plugin's own default tab the same way a bare "dashboard"-less request
 * does for the current plugin (first <tab> in its conf/menu.xml, or "m0").
 *
 * Both segments are whitelisted to letters/digits/dash/underscore before being
 * used in a filesystem path, and the resolved file's existence is checked with
 * is_file() before require() — this blocks path traversal / null-byte tricks
 * and turns an unknown id into an inline message instead of a fatal error.
 *
 * File naming: dashboard-<id>.inc.php (dash) is tried first; dashboard_<id>.inc.php
 * (underscore) is accepted as a fallback, matching the convention used by
 * camila/admin/dashboard_<id>.inc.php — so a plugin built either way works.
 *
 * When a namespaced id resolves to a *different* plugin, the tab bar printed
 * is that target plugin's own conf/menu.xml, not the calling plugin's — you
 * end up looking at the target plugin's dashboard, so its own tabs (not the
 * ones you navigated away from) are what let you keep browsing it. Every tab
 * link in that borrowed menu is also rewritten to carry the "<plugin>--"
 * prefix, otherwise clicking one would be read as a bare, local id by
 * whichever plugin is actually dispatching and fail to resolve.
 */

if (!isset($_camilaPluginDir) || !is_dir($_camilaPluginDir)) {
    trigger_error('plugin_dashboards.inc.php requires $_camilaPluginDir (the including plugin\'s own __DIR__) to be set before it is included', E_USER_ERROR);
}

if (!function_exists('camila_resolve_default_dashboard_id')) {
    /**
     * Default dashboard id for a plugin with no "dashboard" request param (or
     * a namespaced "<plugin>--" with no dashboard part): the id of the first
     * <tab> in that plugin's own conf/menu.xml, so each plugin's landing tab
     * is whatever it declares first — no more assuming every plugin names it
     * "m0". Falls back to "m0" if the menu is missing, empty, or the id fails
     * the same path-safe whitelist used below.
     */
    function camila_resolve_default_dashboard_id(string $menuXmlFile): string {
        if (is_file($menuXmlFile)) {
            $xml = @simplexml_load_file($menuXmlFile);
            if ($xml !== false && isset($xml->tab[0]->id)) {
                $id = trim((string)$xml->tab[0]->id);
                if ($id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                    return $id;
                }
            }
        }
        return 'm0';
    }
}

if (!function_exists('camila_find_dashboard_file')) {
    /**
     * Locate a plugin's dashboard file for a given id: dashboard-<id>.inc.php
     * (dash) first, dashboard_<id>.inc.php (underscore) as a fallback — the
     * convention camila/admin/dashboard_<id>.inc.php uses. Returns null if
     * neither exists.
     */
    function camila_find_dashboard_file(string $plugin, string $dashboard): ?string {
        $base = CAMILA_HOMEDIR . '/plugins/' . $plugin . '/dashboard';
        foreach (['-', '_'] as $sep) {
            $file = $base . $sep . $dashboard . '.inc.php';
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }
}

if (!function_exists('camila_resolve_plugin_dashboard_target')) {
    /**
     * Resolve a "dashboard" request param to the plugin + dashboard id + file
     * it points at. Returns null if malformed or the file doesn't exist.
     */
    function camila_resolve_plugin_dashboard_target(string $thisPlugin, string $raw): ?array {
        $safe  = '/^[A-Za-z0-9_-]+$/';
        $parts = explode('--', $raw, 2);

        if (count($parts) === 2) {
            [$plugin, $dashboard] = $parts;
            if (!preg_match($safe, $plugin)) {
                return null;
            }
            if ($dashboard === '') {
                // "<plugin>--" -> that plugin's own home/default tab
                $dashboard = camila_resolve_default_dashboard_id(CAMILA_HOMEDIR . '/plugins/' . $plugin . '/conf/menu.xml');
            }
        } else {
            $plugin    = $thisPlugin;
            $dashboard = $parts[0];
        }

        if (!preg_match($safe, $plugin) || !preg_match($safe, $dashboard)) {
            return null;
        }

        $file = camila_find_dashboard_file($plugin, $dashboard);
        if ($file === null) {
            return null;
        }

        return ['plugin' => $plugin, 'dashboard' => $dashboard, 'file' => $file];
    }
}

if (!function_exists('camila_build_namespaced_menu_datauri')) {
    /**
     * A copy of $menuXmlFile with every tab's <url> rewritten from
     * "?dashboard=<id>" to "?dashboard=<pluginName>--<id>", returned as a
     * data:// URI (readable by file_get_contents(), which is how
     * printHomeMenu() loads its menu.xml).
     *
     * Needed when showing another plugin's tab bar: that menu.xml's own
     * <url> values are meant to be requested against *that* plugin's own
     * dashboards.inc.php. Rendered as-is inside a different plugin's request
     * context, clicking any of those tabs would be read as a bare, local id
     * by the plugin that's actually dispatching and 404 — see UC discussion.
     * Rewriting the href up front keeps every subsequent click correctly
     * namespaced. Returns null if the menu can't be read.
     */
    function camila_build_namespaced_menu_datauri(string $menuXmlFile, string $pluginName): ?string {
        if (!is_file($menuXmlFile)) {
            return null;
        }
        $xml = @simplexml_load_file($menuXmlFile);
        if ($xml === false) {
            return null;
        }
        foreach ($xml->tab as $tab) {
            $tab->url = '?dashboard=' . $pluginName . '--' . (string)$tab->id;
        }
        return 'data://text/plain;base64,' . base64_encode($xml->asXML());
    }
}

if (!function_exists('camila_print_target_tab_bar')) {
    /**
     * CamilaUserInterface::printHomeMenu() highlights the active tab by
     * checking whether $_SERVER['QUERY_STRING'] contains that tab's own
     * "?dashboard=<id>" url. A namespaced request like "worktable--mcp"
     * never matches that literally, so for the duration of this call only we
     * present the resolved, un-namespaced id in its place — the browser's
     * address bar and the real request are left untouched.
     */
    function camila_print_target_tab_bar(CamilaUserInterface $camilaUI, string $menuXmlFile, string $activeDashboardId) {
        $hadQueryString   = array_key_exists('QUERY_STRING', $_SERVER);
        $savedQueryString = $_SERVER['QUERY_STRING'] ?? null;

        $_SERVER['QUERY_STRING'] = 'dashboard=' . rawurlencode($activeDashboardId);
        $result = $camilaUI->printHomeMenu($menuXmlFile);

        if ($hadQueryString) {
            $_SERVER['QUERY_STRING'] = $savedQueryString;
        } else {
            unset($_SERVER['QUERY_STRING']);
        }

        return $result;
    }
}

$camilaUI = new CamilaUserInterface();
$_CAMILA['page']->camila_export_enabled = false;
$_isTotemUser = strncasecmp($_CAMILA['user'] ?? '', 'totem', 5) === 0;
$_thisPlugin  = basename($_camilaPluginDir);
$_menuXml     = CAMILA_HOMEDIR . '/plugins/' . $_thisPlugin . '/conf/menu.xml';

if (isset($_REQUEST['dashboard'])) {
    $dashboardParam = is_string($_REQUEST['dashboard']) ? $_REQUEST['dashboard'] : '';
    $target         = camila_resolve_plugin_dashboard_target($_thisPlugin, $dashboardParam);

    if ($target === null) {
        if (!$_isTotemUser) $currentTab = $camilaUI->printHomeMenu($_menuXml);
        $_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML,
            '<div class="container pt-4"><article class="message is-danger">'
            . '<div class="message-body">Dashboard not found.</div></article></div>'
        ));
    } else {
        // Show the tab bar of whichever plugin's dashboard we're actually
        // about to display, not necessarily the calling plugin's own, with
        // its own resolved dashboard id highlighted as the active tab.
        $targetMenuXml = CAMILA_HOMEDIR . '/plugins/' . $target['plugin'] . '/conf/menu.xml';

        if ($target['plugin'] === $_thisPlugin) {
            $menuToPrint = $targetMenuXml;
            $activeId    = $target['dashboard'];
        } else {
            // Foreign plugin: its own menu.xml links to bare "?dashboard=<id>",
            // which this plugin's dispatcher would misread as a local id, so
            // print a version with every link re-namespaced "<plugin>--<id>".
            $menuToPrint = camila_build_namespaced_menu_datauri($targetMenuXml, $target['plugin']) ?? $targetMenuXml;
            $activeId    = $target['plugin'] . '--' . $target['dashboard'];
        }

        if (!$_isTotemUser) $currentTab = camila_print_target_tab_bar($camilaUI, $menuToPrint, $activeId);
        require($target['file']);
    }
} else {
    $defaultId   = camila_resolve_default_dashboard_id($_menuXml);
    $defaultFile = camila_find_dashboard_file($_thisPlugin, $defaultId);
    if (!$_isTotemUser) $currentTab = $camilaUI->printHomeMenu($_menuXml, $defaultId);

    if ($defaultFile === null) {
        $_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML,
            '<div class="container pt-4"><article class="message is-danger">'
            . '<div class="message-body">Dashboard not found.</div></article></div>'
        ));
    } else {
        require($defaultFile);
    }
}
