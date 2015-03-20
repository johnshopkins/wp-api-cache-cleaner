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
    $extra = $this->warmCacheForm();

    $this->menuPage = new \WPUtilities\Admin\Settings\SubMenuPage(
      "tools.php",
      "API Cache",
      "API Cache",
      "activate_plugins",
      "api-cache",
      $extra
    );

    $this->createSettingsSection();
  }

  protected function warmCacheForm()
  {
    $html = "<h3>Warm Cache</h3>";
    $html .= '<form method="post" action="' . $this->wordpress->admin_url("admin-post.php") . '">';
    $html .= '<input type="hidden" name="action" value="wp_api_cache_cleaner_warm">';
    $html .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Warm Cache" /></p>';
    $html .= '</form>';

    return $html;
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
