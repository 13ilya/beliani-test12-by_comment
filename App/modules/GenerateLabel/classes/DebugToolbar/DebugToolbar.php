<?php
namespace label\DebugToolbar;

use DebugBar\StandardDebugBar;

/**
 * Class DebugToolbar, singleton pattern.
 */
class DebugToolbar
{
    private static $instance;
    private static $enableKeyName = 'toolbar_enabled';
    private static $traceKeyName = 'toolbar_trace';

    const TRACE_MYSQL = 'mysql';
    const TRACE_SPHINX = 'sphinx';

    /**
     * Return instance of current class
     * @return StandardDebugBar
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new \label\DebugToolbar\CustomDebugBar();
        }
        return self::$instance;
    }

    /**
     * Check if toolbar enabled in current configuration
     * @param string|null $trace if it passed - function returns: is toolbar enabled && is passed trace enabled.
     * @return bool
     */
    public static function isEnabled($trace = null)
    {
        if (DEBUG_TOOLBAR_ENABLED == 2) {
            if (self::getFrontendOption() === 0) {
                return false;
            }
            return self::isTraceEnabled($trace);
        } elseif (DEBUG_TOOLBAR_ENABLED == 1) {
            if (self::getFrontendOption() === 1) {
                return self::isTraceEnabled($trace);
            }
            return false;
        }
        return false;
    }

    /**
     * Check if trace enabled, based on cookies
     * @param string $trace
     * @return bool
     */
    private static function isTraceEnabled($trace)
    {
        if (empty($trace)) {
            return true;
        }

        if (empty($_COOKIE[self::$traceKeyName])) {
            return true;
        }

        $traces = explode(',', $_COOKIE[self::$traceKeyName]);
        foreach ($traces as $v) {
            if ($trace === $v) {
                return true;
            } elseif ('-'.$trace === $v) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return assets for rendering toolbar
     * @return string
     */
    public static function renderHead()
    {
        return '
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/vendor/font-awesome/css/font-awesome.min.css">
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/vendor/highlightjs/styles/github.css">
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/debugbar.css">
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/widgets.css">
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/openhandler.css">
            <link rel="stylesheet" type="text/css" href="/js/debugtoolbar/widgets/sqlqueries/widget.css">
            <script type="text/javascript" src="/js/debugtoolbar/vendor/highlightjs/highlight.pack.js"></script>
            <script type="text/javascript" src="/js/debugtoolbar/debugbar.js"></script>
            <script type="text/javascript" src="/js/debugtoolbar/widgets.js"></script>
            <script type="text/javascript" src="/js/debugtoolbar/openhandler.js"></script>
            <script type="text/javascript" src="/js/debugtoolbar/widgets/sqlqueries/widget.js"></script>';
    }

    /**
     * Get options about is enabled toolbar from frotend.
     * @return int | null
     */
    private static function getFrontendOption()
    {
        $cookie = isset($_COOKIE[self::$enableKeyName]) ? $_COOKIE[self::$enableKeyName] : null;
        $post = isset($_POST[self::$enableKeyName]) ? $_POST[self::$enableKeyName] : null;
        $get = isset($_GET[self::$enableKeyName]) ? $_GET[self::$enableKeyName] : null;
        if (is_null($cookie) && is_null($get) && is_null($post)) {
            return null;
        }
        $result = max($cookie, $get, $post);
        if (is_null($result)) {
            return null;
        }
        return (int)$result;
    }

    private function __construct() {}
    private function __clone() {}
}