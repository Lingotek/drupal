<?php

/**
 * @file
 * Defines Lingotek.
 */
 
/**
 * A utility class for Lingotek translation.
 */
class Lingotek {
  /**
   * A map of Drupal language codes to Lingotek language codes.
   */
  private static $language_map = array(
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
    'Ilo' => 'Ilo_PH',
    'is' => 'is_IS',
    'it' => 'it_IT',
    'ja' => 'ja_JP',
    'jv' => 'jv_ID',
    'ka' => 'ka_GE',
    'kek' => 'kek_GT',
    'kg' => 'kg_CD',
    'ki' => 'kik_KE',
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
    'pt-pt' => 'pt_PT',
    'pt-br' => 'pt_BR',
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
    'zh' => 'zh_TW',
    'zh-hans' => 'zh_CN',
    'zh-hant' => 'zh_TW',
    'zu' => 'zu_ZA',
   );
   
  
  /**
   * Gets the Lingotek language code for the specified Drupal language code.
   *
   * @param string $language_code
   *   A Drupal language code.
   *
   * @return mixed
   *   The Lingotek language code if there is a match for the passed language code,
   *   FALSE otherwise.
   */
  public static function getLingotekLanguage($language_code) {
    
    $lingotek_languge = FALSE;
    
    if (isset(self::$language_map[$language_code])) {
      $lingotek_language = self::$language_map[$language_code];
    }
    
    return $lingotek_language;
  }
  
  /**
   * Gets the site's available target languages for Lingotek translation.
   *
   * @return array
   *   An array of Lingotek language codes.
   */
  public static function availableLanguageTargets() {
    $languages = array();
    
    foreach (language_list() as $target_language) {
      if ($target_language->enabled && lingotek_supported_language($target_language->language)) {
        $languages[] = self::getLingotekLanguage($target_language->language);
      }
    }
    
    return $languages;
  }
}
