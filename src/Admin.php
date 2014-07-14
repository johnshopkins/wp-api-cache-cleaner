<?php

namespace CacheCleaner;

class Admin
{
  protected $wordpress;

  protected $menuPage;
  protected $postTypesSection;

  public function __construct()
  {
    $this->wordpress = isset($args["wordpress"]) ? $args["wordpress"] : new \WPUtilities\WordPressWrapper();
    $this->createMenuPage();
  }

  protected function createMenuPage()
  {
    $extra = '<form method="post" action="' . $this->wordpress->admin_url("admin-post.php") . '">';
    $extra .= '<input type="hidden" name="action" value="wp_api_cache_cleaner_warm">';
    $extra .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Warm Cache" /></p>';
    $extra .= '</form>';

    $this->menuPage = new \WPUtilities\Admin\Settings\SubMenuPage(
      "options-general.php",
      "API Cache Options",
      "API Cache",
      "activate_plugins",
      "api-cache",
      $extra
    );

    $this->createSettingsSection();
  }

  protected function createSettingsSection()
  {
    $this->postTypesSection = new \WPUtilities\Admin\Settings\Section(
        $this->menuPage,
        "settings",
        "No settings at this time",
        array()
    );
  }

}
