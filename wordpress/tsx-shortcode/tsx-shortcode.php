<?php
/*
Plugin Name: TsxShortCode
Description: Adds a ShortCode for loading tsx-files
Version: 0.1.0
Author: "Puggan Sundragon" <puggan@sundragon.se>
Author URI: https://github.com/puggan/
License: MIT
Requires PHP: 8.0
*/

namespace Puggan\TsxShortCode;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/TsxShortCode.php';

TsxShortCode::init();
