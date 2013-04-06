<?php
/**
 * @file loginza.theme.inc
 *    Theme routines.
 */

/**
 * Loginza Iframe Widget theme.
 *
 * @param array $variables
 * @return string
 */
function theme_loginza_iframe($variables) {
  $widget = <<<LW
<script src="http://loginza.ru/js/widget.js" type="text/javascript"></script>
<iframe src="http://loginza.ru/api/widget?overlay=loginza&@providerslang=@lang&token_url=@token_url"
style="width:359px;height:180px;" scrolling="no" frameborder="no"></iframe>
LW;

  return _theme_loginza_widget($widget, $variables);
}

/**
 * Loginza Button Widget theme.
 *
 * @param array $variables
 * @return string
 */
function theme_loginza_button($variables) {
  $widget = <<<LW
<script src="http://loginza.ru/js/widget.js" type="text/javascript"></script>
<a href="http://loginza.ru/api/widget?@providerslang=@lang&token_url=@token_url" class="loginza">
    <img src="http://loginza.ru/img/sign_in_button_gray.gif" alt="@caption" title="@caption"/>
</a>
LW;

  return _theme_loginza_widget($widget, $variables);
}

/**
 * Loginza Icons Widget theme.
 *
 * @param array $variables
 * @return string
 */
function theme_loginza_icons($variables) {
  $widget = <<<LW
<script src="http://loginza.ru/js/widget.js" type="text/javascript"></script>
@caption
<a href="https://loginza.ru/api/widget?@providerslang=@lang&token_url=@token_url" class="loginza">
    @icons
</a>
LW;

  return _theme_loginza_widget($widget, $variables);
}

/**
 * Generic theme for all wodgets.
 *
 * @param string $widget
 * @param array $variables
 * @return string
 */
function _theme_loginza_widget($widget, $variables) {
  $values = array(
    '@token_url' => _loginza_param_token_url($variables['token_url']),
    '@providers' => _loginza_param_providers($variables['providers_set'], $variables['provider']),
    '@lang' => _loginza_param_lang($variables['lang']),
  );

  $keys = array_keys($variables);
  if (in_array('caption', $keys)) {
    $values['@caption'] = empty($variables['caption'])
        ? variable_get('loginza_caption_text', t('Log in with Loginza'))
        : $variables['caption'];
    $values['@caption'] = check_plain($values['@caption']);
  }

  if (in_array('icons', $keys)) {
    $all_providers = loginza_providers_list();

    $providers_list = empty($variables['icons']) ? $variables['providers_set'] : array_unique($variables['icons']);
    if (empty($providers_list)) {
      $providers_set = array_filter(variable_get('loginza_providers_set', array()));
      $providers_icons = array_filter(variable_get('loginza_providers_icons', array()));
      $providers_list = empty($providers_set) ? $providers_icons : $providers_set;
    }
    if (empty($providers_list)) {
      $providers_list = array_keys($all_providers);
    }

    // TODO: remove this workaround after Loginza will fix issue with disappeared icons
    foreach (array('verisign', 'aol') as $p) {
      $idx = array_search($p, $providers_list);
      if ($idx !== FALSE) unset($providers_list[$idx]);
    }

    $icons = array();
    foreach ($providers_list as $provider) {
      $icons[] = theme('image', array(
        'path' => sprintf('http://loginza.ru/img/providers/%s.png', $provider),
        'alt' => $all_providers[$provider],
        'title' => $all_providers[$provider],
      ));
    }

    $values['@icons'] = implode("\r\n", $icons);
  }

  return strtr($widget, $values);
}
