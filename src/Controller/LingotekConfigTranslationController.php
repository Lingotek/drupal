<?php

namespace Drupal\lingotek\Controller;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\Controller\ConfigTranslationController;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

class LingotekConfigTranslationController extends ConfigTranslationController {

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek config translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translationService
   */
  protected $translationService;

  /**
   * Constructs a LingotekConfigTranslationController.
   *
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *  The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $config_mapper_manager
   *   The configuration mapper manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The menu link access service.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The dynamic router service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigTranslationServiceInterface $translation_service, ConfigMapperManagerInterface $config_mapper_manager, AccessManagerInterface $access_manager, RequestMatcherInterface $router, InboundPathProcessorInterface $path_processor, AccountInterface $account, LanguageManagerInterface $language_manager, RendererInterface $renderer) {
    parent::__construct($config_mapper_manager, $access_manager, $router, $path_processor, $account, $language_manager, $renderer);
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->translationService = $translation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.config_translation'),
      $container->get('plugin.manager.config_translation.mapper'),
      $container->get('access_manager'),
      $container->get('router'),
      $container->get('path_processor_manager'),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('renderer')
    );
  }

  public function itemPage(Request $request, RouteMatchInterface $route_match, $plugin_id) {
    $page = parent::itemPage($request, $route_match, $plugin_id);
    $entity = NULL;
    $entity_id = NULL;
    /** @var \Drupal\config_translation\ConfigMapperInterface $mapper */
    $mapper = $this->configMapperManager->createInstance($plugin_id);
    $mapper->populateFromRouteMatch($route_match);

    $languages = $this->languageManager->getLanguages();
    $original_langcode = $mapper->getLangcode();
    if (!isset($languages[$original_langcode])) {
      // If the language is not configured on the site, create a dummy language
      // object for this listing only to ensure the user gets useful info.
      $language_name = $this->languageManager->getLanguageName($original_langcode);
      $languages[$original_langcode] = new Language(array(
        'id' => $original_langcode,
        'name' => $language_name
      ));
    }
    if ($mapper instanceof ConfigEntityMapper) {
      /** @var $mapper ConfigEntityMapper */
      $entity = $mapper->getEntity();
      $entity_id = $entity->id();
    }

    foreach ($languages as $language) {
      $langcode = $language->getId();
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
      if ($locale && $langcode === $original_langcode) {
        if ($entity_id === NULL) {
          $entity_id = $plugin_id;
        }
        $page['languages'][$langcode]['operations']['#links']['upload'] = array(
          'title' => $this->t('Upload'),
          'url' => Url::fromRoute('lingotek.config.upload',
            [
              'entity_type' => $plugin_id,
              'entity_id' => $entity_id,
            ]),
        );
        if ($entity && $document_id = $this->translationService->getDocumentId($entity)) {
          $page['languages'][$langcode]['operations']['#links']['check_upload'] = array(
            'title' => $this->t('Check upload status'),
            'url' => Url::fromRoute('lingotek.config.check_upload',
              [
                'entity_type' => $plugin_id,
                'entity_id' => $entity_id,
              ]),
          );
        }
        // If it's a ConfigNamesMapper, we have to call a different method.
        elseif ($entity_id === $plugin_id) {
          if ($document_id = $this->translationService->getConfigDocumentId($mapper)) {
            $page['languages'][$langcode]['operations']['#links']['check_upload'] = array(
              'title' => $this->t('Check upload status'),
              'url' => Url::fromRoute('lingotek.config.check_upload',
                [
                  'entity_type' => $plugin_id,
                  'entity_id' => $entity_id,
                ]),
            );
          }
        }
      }
      if ($locale && $langcode !== $original_langcode) {
        if (isset($page['languages'][$langcode]['operations']['#links']['add'])) {
          // If we have a config entity and it has a document id, we want to show
          // the ability of requesting translations.
          if ($entity && $document_id = $this->translationService->getDocumentId($entity)) {
            $target_status = $this->translationService->getTargetStatus($entity, $langcode);
            $this->generateOperationsLinks($page, $plugin_id, $entity_id, $target_status, $langcode, $locale);
          }
          // If it is a ConfigNamesMapper object, we need to call another method.
          elseif ($entity_id === $plugin_id && $document_id = $this->translationService->getConfigDocumentId($mapper)) {
            $target_status = $this->translationService->getConfigTargetStatus($mapper, $langcode);
            $this->generateOperationsLinks($page, $plugin_id, $entity_id, $target_status, $langcode, $locale);
          }
        }
      }
    }
    return $page;
  }

  public function upload($entity_type, $entity_id, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      $mappers =  $this->configMapperManager->getMappers();
      if ($this->translationService->getConfigDocumentId($mappers[$entity_type])) {
        try {
          if ($this->translationService->updateConfig($entity_type)) {
            drupal_set_message($this->t('%label has been updated.', ['%label' => $definition['title']]));
          }
        }
        catch (LingotekApiException $e) {
          drupal_set_message($this->t('%label update failed. Please try again.',
            ['%label' => $mappers[$entity_type]->getTitle()]), 'error');
        }
      }
      else {
        try {
          if ($this->translationService->uploadConfig($entity_type)) {
            drupal_set_message($this->t('%label uploaded successfully', ['%label' => $definition['title']]));
          }
        }
        catch (LingotekApiException $e) {
          drupal_set_message($this->t('%label upload failed. Please try again.',
            ['%label' => $mappers[$entity_type]->getTitle()]), 'error');
        }
      }
      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    if ($this->translationService->getDocumentId($entity)) {
      try {
        if ($this->translationService->updateDocument($entity)) {
          drupal_set_message($this->t('%label has been updated.', ['%label' => $entity->label()]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label update failed. Please try again.',
          ['%label' => $entity->label()]), 'error');
      }
    }
    else {
      try {
        if ($this->translationService->uploadDocument($entity)) {
          drupal_set_message($this->t('%label uploaded successfully', ['%label' => $entity->label()]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label upload failed. Please try again.',
          ['%label' => $entity->label()]), 'error');
      }
    }
    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }

  public function update($entity_type, $entity_id, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      try {
        if ($this->translationService->updateConfig($entity_type)) {
          drupal_set_message($this->t('%label has been updated.', ['%label' => $definition['title']]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label update failed. Please try again.',
          ['%label' => $definition['title']]), 'error');
      }
      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($this->translationService->updateDocument($entity)) {
        drupal_set_message($this->t('%label has been updated.', ['%label' => $entity->label()]));
      }
    }
    catch (LingotekApiException $e) {
      drupal_set_message($this->t('%label update failed. Please try again.',
        ['%label' => $entity->label()]), 'error');
    }
    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }

  public function checkUpload($entity_type, $entity_id, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      try {
        if ($this->translationService->checkConfigSourceStatus($entity_type)) {
          drupal_set_message($this->t('%label status checked successfully', ['%label' => $definition['title']]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label status check failed. Please try again.',
          ['%label' => $definition['title']]), 'error');
      }
      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($this->translationService->checkSourceStatus($entity)) {
        drupal_set_message($this->t('%label status checked successfully', ['%label' => $entity->label()]));
      }
    }
    catch (LingotekApiException $e) {
      drupal_set_message($this->t('%label status check failed. Please try again.',
        ['%label' => $entity->label()]), 'error');
    }

    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }

  public function request($entity_type, $entity_id, $locale, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      try {
        if ($this->translationService->addConfigTarget($entity_id, $locale)) {
          drupal_set_message($this->t('Translation to %locale requested successfully', ['%locale' => $locale]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label @locale translation request failed. Please try again.',
          ['%label' => $definition['title'], '@locale' => $locale]), 'error');
      }
      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($this->translationService->addTarget($entity, $locale)) {
        drupal_set_message($this->t('Translation to %locale requested successfully', ['%locale' => $locale]));
      }
    }
    catch (LingotekApiException $e) {
      drupal_set_message($this->t('%label @locale translation request failed. Please try again.',
        ['%label' => $entity->label(), '@locale' => $locale]), 'error');
    }

    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }

  public function checkDownload($entity_type, $entity_id, $locale, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      try {
        if ($this->translationService->checkConfigTargetStatus($entity_id, $locale)) {
          drupal_set_message($this->t('Translation to %locale checked successfully', ['%locale' => $locale]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label @locale translation status check failed. Please try again.',
          ['%label' => $entity_id, '@locale' => $locale]), 'error');
      }

      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($this->translationService->checkTargetStatus($entity, $locale)) {
        drupal_set_message($this->t('Translation to %locale status checked successfully', ['%locale' => $locale]));
      }
    }
    catch (LingotekApiException $e) {
      drupal_set_message($this->t('%label @locale translation status check failed. Please try again.',
        ['%label' => $entity->label(), '@locale' => $locale]), 'error');
    }

    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }


  public function download($entity_type, $entity_id,  $locale, Request $request) {
    if ($entity_type === $entity_id) {
      // It is not a config entity, but a config object.
      $definition = $this->configMapperManager->getDefinition($entity_type);
      try {
        if ($this->translationService->downloadConfig($entity_id, $locale)) {
          drupal_set_message($this->t('Translation to %locale downloaded successfully', ['%locale' => $locale]));
        }
      }
      catch (LingotekApiException $e) {
        drupal_set_message($this->t('%label @locale translation download failed. Please try again.',
          ['%label' => $definition['title'], '@locale' => $locale]), 'error');
      }
      return $this->redirectToConfigTranslateOverview($entity_type);
    }
    // Check if it's a field.
    if (substr($entity_type, -7) == '_fields') {
      // Hack for fields, the entity is field config.
      $entity_type = 'field_config';
    }
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($this->translationService->downloadDocument($entity, $locale)) {
        drupal_set_message($this->t('Translation to %locale downloaded successfully', ['%locale' => $locale]));
      }
    }
    catch (LingotekApiException $e) {
      drupal_set_message($this->t('%label @locale translation download failed. Please try again.',
        ['%label' => $entity->label(), '@locale' => $locale]), 'error');
    }

    return $this->redirectToEntityTranslateOverview($entity_type, $entity_id);
  }

  /**
   * Redirect to config entity translation overview page.
   *
   * @param string $entity_type
   *   The config entity type id.
   * @param string $entity_id
   *   The config entity id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  protected function redirectToEntityTranslateOverview($entity_type, $entity_id) {
    $mappers =  $this->configMapperManager->getMappers();
    if ($entity_type === 'field_config') {
      $field_config = FieldConfig::load($entity_id);
      $id = $field_config->getTargetEntityTypeId();
      $mapper = $mappers[$id . '_fields'];
      $mapper->setEntity($field_config);
      $entity_definition =  $this->entityManager->getDefinition($id);

      $uri = Url::fromRoute($mapper->getOverviewRouteName(), [$entity_type => $entity_id, $entity_definition->getBundleEntityType()  => $field_config->getTargetBundle()]);
    }
    else {
      $mapper = $mappers[$entity_type];
      $uri = Url::fromRoute($mapper->getOverviewRouteName(), [$entity_type => $entity_id]);
    }
    return new RedirectResponse($uri->setAbsolute(TRUE)->toString());
  }

  /**
   * Redirect to config translation overview page.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  protected function redirectToConfigTranslateOverview($plugin_id) {
    $mappers =  $this->configMapperManager->getMappers();
    $mapper = $mappers[$plugin_id];
    $uri = Url::fromRoute($mapper->getOverviewRouteName());
    return new RedirectResponse($uri->setAbsolute(TRUE)->toString());
  }

  /**
   * Generates the operation links for each language.
   *
   * @param array &$page
   *   The reference of the page build render array where we need to add the
   *   links to.
   * @param string $plugin_id
   *   The plugin id, which is needed for building the link. It could be the
   *   entity type id or the plugin id of the config mapper.
   * @param string $entity_id
   *   The entity id. If it's a config mapper and not an entity, it would be the
   *   plugin id.
   * @param int $target_status
   *   The target status for this language. This way we decide which link must
   *   be shown.
   * @param $langcode
   *   The langcode of the translation we are building the link to. Used for
   *   keying the link in the page array.
   * @param $locale
   *   Lingotek locale we are creating the link for. Used for building the link
   *   itself.
   */
  protected function generateOperationsLinks(&$page, $plugin_id, $entity_id, $target_status, $langcode, $locale) {
    $route_params = [
      'entity_type' => $plugin_id,
      'entity_id' => $entity_id,
      'locale' => $locale,
    ];
    if ($target_status === NULL || $target_status == Lingotek::STATUS_REQUEST || $target_status == Lingotek::STATUS_UNTRACKED) {
      $page['languages'][$langcode]['operations']['#links']['request'] = array(
        'title' => $this->t('Request translation'),
        'url' => Url::fromRoute('lingotek.config.request', $route_params),
      );
    }
    elseif ($target_status == Lingotek::STATUS_PENDING) {
      $page['languages'][$langcode]['operations']['#links']['check_download'] = array(
        'title' => $this->t('Check Download'),
        'url' => Url::fromRoute('lingotek.config.check_download', $route_params),
      );
    }
    elseif ($target_status == Lingotek::STATUS_READY) {
      $page['languages'][$langcode]['operations']['#links']['download'] = array(
        'title' => $this->t('Download'),
        'url' => Url::fromRoute('lingotek.config.download', $route_params),
      );
    }
  }

}
