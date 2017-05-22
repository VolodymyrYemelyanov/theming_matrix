<?php
/**
 * @file
 * Contains \Drupal\mymodule\Controller\MyPageController class.
 */

namespace Drupal\mymodule\Controller;
use Drupal\Core\Controller\ControllerBase;

class MyPageController extends ControllerBase {
    /**
     * Returns markup for our custom page.
     */
    public function customPage() {
        return [
            '#markup' => t('Hi there! Welcome to my custom page!'),
        ];
    }
}


