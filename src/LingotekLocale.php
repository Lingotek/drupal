<?php

namespace Drupal\lingotek;

/**
 * A utility class for Lingotek translation.
 */
class LingotekLocale {

  /**
   * A map of Drupal language codes to Lingotek language codes.
   */
  protected static $language_map = [
      'aa' => 'aa_DJ',
      'ab' => 'ab_GE',
      'af' => 'af_ZA',
      'ak' => 'ak_GH',
      'am' => 'am_ET',
      'apa' => 'apa_US',
      'ar' => 'ar',
      'as' => 'as_IN',
      'ast' => 'ast_ES',
      'ay' => 'ay_BO',
      'az' => 'az_AZ',
      'ba' => 'ba_RU',
      'be' => 'be_BY',
      'bg' => 'bg_BG',
      'bi' => 'bi_VU',
      'bik' => 'bik_PH',
      'bm' => 'bm_ML',
      'bn' => 'bn_BD',
      'bo' => 'bo_CN',
      'br' => 'br_FR',
      'bs' => 'bs_BA',
      'ca' => 'ca_ES',
      'ce' => 'ce_RU',
      'ceb' => 'ceb_PH',
      'ch' => 'ch_GU',
      'chr' => 'chr_US',
      'chy' => 'chy_US',
      'co' => 'co_FR',
      'cpe' => 'cpe_US',
      'cpf' => 'cpf_MU',
      'cpp' => 'cpp_BR',
      'cs' => 'cs_CZ',
      'cy' => 'cy_GB',
      'da' => 'da_DK',
      'de' => 'de_DE',
      'dik' => 'dik_SD',
      'dv' => 'dv_MV',
      'dz' => 'dz_BT',
      'ee' => 'ee_GH',
      'efi' => 'efi_NG',
      'el' => 'el_GR',
      'en' => 'en_US',
      'eo' => 'eo_FR',
      'es' => 'es_ES',
      'et' => 'et_EE',
      'eu' => 'eu_ES',
      'fa' => 'fa_IR',
      'fat' => 'fat_GH',
      'fi' => 'fi_FI',
      'fj' => 'fj_FJ',
      'fon' => 'fon_BJ',
      'fr' => 'fr_FR',
      'ga' => 'ga_IE',
      'gaa' => 'gaa_GH',
      'gbz' => 'gbz_IR',
      'gd' => 'gd_GB',
      'gil' => 'gil_KI',
      'gl' => 'gl_ES',
      'gn' => 'gn_BO',
      'gu' => 'gu_IN',
      'ha' => 'ha_NG',
      'haw' => 'haw_US',
      'he' => 'he_IL',
      'hi' => 'hi_IN',
      'hil' => 'hil_PH',
      'hmn' => 'hmn_LA',
      'hr' => 'hr_HR',
      'ht' => 'ht_HT',
      'hu' => 'hu_HU',
      'hy' => 'hy_AM',
      'id' => 'id_ID',
      'ig' => 'ig_NG',
      'ilo' => 'Ilo_PH',
      'is' => 'is_IS',
      'it' => 'it_IT',
      'ja' => 'ja_JP',
      'jv' => 'jv_ID',
      'ka' => 'ka_GE',
      'kek' => 'kek_GT',
      'kg' => 'kg_CD',
      'ki' => 'kik_KE',
      'kik' => 'kik_KE',
      'kin' => 'kin_RW',
      'kj' => 'kj_AO',
      'kk' => 'kk_KZ',
      'km' => 'km_KH',
      'kn' => 'kn_IN',
      'ko' => 'ko_KR',
      'kos' => 'kos_FM',
      'ks' => 'ks_IN',
      'ku' => 'ku_IQ',
      'kw' => 'kw_GB',
      'ky' => 'ky_KG',
      'la' => 'la_VA',
      'lb' => 'lb_LU',
      'lg' => 'lg_UG',
      'ln' => 'ln_CD',
      'lo' => 'lo_LA',
      'lt' => 'lt_LT',
      'lu' => 'lu_CD',
      'lv' => 'lv_LV',
      'mg' => 'mg_MG',
      'mh' => 'mh_MH',
      'mi' => 'mi_NZ',
      'mk' => 'mk_MK',
      'ml' => 'ml_IN',
      'mn' => 'mn_MN',
      'mo' => 'mo_MD',
      'mr' => 'mr_IN',
      'ms' => 'ms_MY',
      'mt' => 'mt_MT',
      'my' => 'my_MM',
      'na' => 'na_NR',
      'nb' => 'nb_NO',
      'nd' => 'nd_ZW',
      'ne' => 'ne_NP',
      'ng' => 'ng_NA',
      'niu' => 'niu_NU',
      'nl' => 'nl_NL',
      'nn' => 'nn_NO',
      'no' => 'no_NO',
      'nr' => 'nr_ZA',
      'nso' => 'nso_ZA',
      'nv' => 'nv_US',
      'ny' => 'ny_MW',
      'om' => 'om_ET',
      'or' => 'or_IN',
      'pa' => 'pa_IN',
      'pag' => 'pag_PH',
      'pap' => 'pap_AN',
      'pau' => 'pau_PW',
      'pl' => 'pl_PL',
      'ps' => 'ps_AF',
      'pt' => 'pt_BR',
      'qu' => 'qu_BO',
      'rar' => 'rar_CK',
      'rn' => 'rn_BI',
      'ro' => 'ro_RO',
      'ru' => 'ru_RU',
      'sa' => 'sa_IN',
      'sc' => 'sc_IT',
      'scn' => 'scn_IT',
      'sd' => 'sd_PK',
      'sg' => 'sg_CF',
      'si' => 'si_LK',
      'sk' => 'sk_SK',
      'sl' => 'sl_SI',
      'sm' => 'sm_WS',
      'sn' => 'sn_ZW',
      'so' => 'so_SO',
      'sq' => 'sq_SQ',
      'sr' => 'sr_CS',
      'ss' => 'ss_SZ',
      'st' => 'st_LS',
      'su' => 'su_ID',
      'sv' => 'sv_SE',
      'sw' => 'sw_TZ',
      'ta' => 'ta_IN',
      'te' => 'te_IN',
      'tg' => 'tg_TJ',
      'th' => 'th_TH',
      'ti' => 'ti_ER',
      'tk' => 'tk_TM',
      'fil' => 'tl_PH',
      'tl' => 'tl_PH',
      'tn' => 'tn_BW',
      'to' => 'to_TO',
      'tpi' => 'tpi_PG',
      'tr' => 'tr_TR',
      'ts' => 'ts_ZA',
      'tum' => 'tum_MW',
      'tvl' => 'tvl_TV',
      'tw' => 'tw_GH',
      'ty' => 'ty_PF',
      'ug' => 'ug_CN',
      'uk' => 'uk_UA',
      'um' => 'um_AO',
      'ur' => 'ur_PK',
      'uz' => 'uz_UZ',
      've' => 've_ZA',
      'vi' => 'vi_VN',
      'war' => 'war_PH',
      'wo' => 'wo_SN',
      'xh' => 'xh_ZA',
      'yap' => 'yap_FM',
      'yi' => 'yi_IL',
      'yo' => 'yo_NG',
      'zh' => 'zh_CN',
      'zh-hans' => 'zh_CN',
      'zh-hant' => 'zh_TW',
      'zu' => 'zu_ZA',
  ];
  public static $language_mapping_l2d_exceptions = [
      'ar' => 'ar',
      'zh_CN' => 'zh-hans',
      'zh_TW' => 'zh-hant',
  ];
  public static $language_mapping_d2l_exceptions = [
      'zh-hans' => 'zh_CN',
      'zh-hant' => 'zh_TW',
      'zh_HANS' => 'zh_CN',
      'zh_HANT' => 'zh_TW',
  ];

  /**
   * Converts the Lingotek language code for the specified Drupal language code.
   *
   * @param string $drupal_language_code
   *   A Drupal language code.
   *
   * @return mixed
   *   The Lingotek language code if there is a match for the passed language code,
   *   FALSE otherwise.
   */
  public static function convertDrupal2Lingotek($drupal_language_code) {
    $lingotek_locale = $drupal_language_code;

    $exceptions = self::$language_mapping_d2l_exceptions;
    if (array_key_exists($drupal_language_code, $exceptions)) {
      $lingotek_locale = $exceptions[$drupal_language_code];
    }
    else {
      // If the code contains a dash then, keep it specific
      $dash_pos = strpos($drupal_language_code, "-");
      if ($dash_pos !== FALSE) {
        $lang = substr($drupal_language_code, 0, $dash_pos);
        $loc = strtoupper(substr($drupal_language_code, $dash_pos + 1));
        $lingotek_locale = $lang . '_' . $loc;
      }
      // If it is generic then use the mapping to pick a specific
      elseif (isset(self::$language_map[$drupal_language_code])) {
        $lingotek_locale = self::$language_map[$drupal_language_code];
      }
    }
    return $lingotek_locale;
  }

  /**
   * Gets the Drupal language code for the specified Lingotek language code.
   *
   * @param string $lingotek_locale
   *   A Lingotek language code. (e.g., 'de_DE', 'pt_BR', 'fr_FR')
   *
   * @return mixed
   *   The Drupal language code if there is a match for the passed language code, (e.g., 'de-de', 'pt-br',' fr-fr')
   *   FALSE otherwise.
   */
  public static function convertLingotek2Drupal($lingotek_locale, $generate = FALSE) {
    $installed_languages = \Drupal::languageManager()->getLanguages();
    // standard conversion
    $drupal_language_code = strtolower(str_replace("_", "-", $lingotek_locale));
    if (isset($installed_languages[$drupal_language_code])) {
      return $installed_languages[$drupal_language_code]->getId();
    }
    $drupal_general_code = substr($drupal_language_code, 0, strpos($drupal_language_code, '-'));
    $exceptions = self::$language_mapping_l2d_exceptions;
    if (array_key_exists($lingotek_locale, $exceptions)) {
      return $exceptions[$lingotek_locale];
    }
    else {
      $flipped_map = array_flip(self::$language_map);
      if (isset($flipped_map[$lingotek_locale])) {
        return $flipped_map[$lingotek_locale];
      }
    }
    return $drupal_general_code;
  }

  public static function generateLingotek2Drupal($lingotek_locale) {
    // standard conversion
    $drupal_language_code = strtolower(str_replace("_", "-", $lingotek_locale));
    if ($hyphen_index = strpos($drupal_language_code, '-') > 0) {
      $drupal_general_code = substr($drupal_language_code, 0, strpos($drupal_language_code, '-'));
    }
    else {
      // We try with the given language, if we didn't have an hyphen (e.g. ar).
      $drupal_general_code = $drupal_language_code;
    }

    // check enabled list
    $enabled_languages = \Drupal::languageManager()->getLanguages();
    $enabled_codes = array_keys($enabled_languages);

    if (!in_array($drupal_general_code, $enabled_codes)) {
      return $drupal_general_code;
    }
    elseif (!in_array($drupal_language_code, $enabled_codes)) {
      return $drupal_language_code;
    }
    else {
      return $drupal_language_code . rand(10, 99);
    }
  }

  public static function testConvertFunctions() {
    $result = [
        "drupal => lingotek" => [],
        "lingotek => drupal" => [],
    ];
    // drupal => lingotek
    foreach (self::$language_map as $drupal_language_code => $lingotek_locale) {
      $ret_lingotek_locale = self::convertDrupal2Lingotek($drupal_language_code);
      if (strcmp($lingotek_locale, $ret_lingotek_locale) !== 0) {
        $result["drupal => lingotek"][] = "[$drupal_language_code => $ret_lingotek_locale] !== $lingotek_locale";
      }
    }
    // lingotek => drupal
    foreach (self::$language_map as $drupal_language_code => $lingotek_locale) {
      $ret_drupal_language_code = self::convertLingotek2Drupal($lingotek_locale);
      if (strcmp($drupal_language_code, $ret_drupal_language_code) !== 0) {
        $result["lingotek => drupal"][] = "[$lingotek_locale => $ret_drupal_language_code] !== $drupal_language_code";
      }
    }

    return $result;
  }

  /**
   * Returns whether the given language is supported.
   *
   * @return
   *   Boolean value.
   */
  public static function isSupportedLanguage($drupal_language_code, $enabled = TRUE) {
    // ($drupal_language_code != LANGUAGE_NONE)
    $supported = (self::convertDrupal2Lingotek($drupal_language_code, $enabled) !== FALSE);
    if (!$supported) {
      LingotekLog::warning("Unsupported language detected: [@language]", ['@language' => $drupal_language_code]);
    }
    return $supported;
  }

  /**
   * Gets the site's available target languages for Lingotek translation.
   *
   * @param mixed $pluck_field
   *   NULL - return the entire object
   *   string - return an array of just the pluck_field specified (if it exists)
   *   array - return an array of the selected fields
   *
   * @return array
   *   An array of Lingotek language codes.
   */
  public static function getLanguages($pluck_field = NULL, $include_disabled = FALSE, $lingotek_locale_to_exclude = NULL) {
    // lingotek_add_missing_locales(FALSE);
    $languages = [];

    foreach (\Drupal::languageManager()->getLanguages() as $target_language) {
      if ($target_language->lingotek_locale == $lingotek_locale_to_exclude) {
        continue;
      }
      $language = (is_string($pluck_field) && isset($target_language->$pluck_field)) ? $target_language->$pluck_field : $target_language;
      // include all languages enabled
      if ($target_language->lingotek_enabled) {
        $languages[$target_language->lingotek_locale] = $language;
        // include all languages, including disabled (lingotek_enabled is 0)
      }
      elseif ($include_disabled) {
        $languages[$target_language->lingotek_locale] = $language;
      }
    }
    return $languages;
  }

  public static function getLanguagesWithoutSource($source_lingotek_locale) {
    return self::getLanguages('lingotek_locale', FALSE, $source_lingotek_locale);
  }

  public static function getLanguagesWithoutSourceAsJSON($source_lingotek_locale) {
    return drupal_json_encode(array_values(self::getLanguages('lingotek_locale', FALSE, $source_lingotek_locale)));
  }

}
