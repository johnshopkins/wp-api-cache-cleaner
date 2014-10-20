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
      "options-general.php",
      "API Cache Options",
      "API Cache",
      "activate_plugins",
      "api-cache",
      $extra
    );

    $this->createSettingsSection();
  }

  protected function warmCacheForm()
  {
    $post_types = $this->wordpress->get_post_types(array("show_in_menu" => "content"), "objects");
    $defaultOn = array("page", "attachment", "block", "button", "club", "collection", "division", "fact", "field_of_study", "location", "map", "news_bar", "person", "quote", "related_content", "search_response", "teaser", "timeline_event");

    $html = "<h3>Warm Cache</h3>";
    $html .= "<p>Select the content types to warm from the list below.</p>";
    $html .= '<form method="post" action="' . $this->wordpress->admin_url("admin-post.php") . '">';
    $html .= '<input type="hidden" name="action" value="wp_api_cache_cleaner_warm">';

    foreach ($post_types as $type => $details) {
      $checked = in_array($type, $defaultOn) ? "checked='checked'" : "";
      $html .= "<input type='checkbox' name='{$type}' id='{$type}' value='1' {$checked} />";
      $html .= " <label for='{$type}'>{$details->label}</label>";
      $html .= "<br>";
    }

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
