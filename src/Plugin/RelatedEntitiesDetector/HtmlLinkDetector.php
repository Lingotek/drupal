<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @RelatedEntitiesDetector (
 *   id = "html_link_detector",
 *   title = @Translation("Get editor linked entities with html links"),
 *   description = @translation("Get editor linked entities with html links."),
 *   weight = 7,
 * )
 */
class HtmlLinkDetector extends EditorDetectorBase {

  /**
   * A Symfony request instance
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal Path Validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["text", "text_long", "text_with_summary"];

  /**
   * NestedEntityReferences constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The EntityRepositoryInterface service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The Drupal Path Validator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, EntityFieldManagerInterface $entity_field_manager, LingotekConfigurationServiceInterface $lingotek_configuration, Request $request, EntityTypeManagerInterface $entity_type_manager, PathValidatorInterface $path_validator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_repository, $entity_field_manager, $lingotek_configuration);
    $this->request = $request;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('entity_field.manager'),
      $container->get('lingotek.configuration'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
      $container->get('path.validator')
    );
  }

  protected function extractEntitiesFromText($text) {
    // This method is adapted from \Drupal\entity_usage\Plugin\EntityUsage\Track\HtmlLink::parseEntitiesFromText().
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $entities = [];

    // Loop trough all the <a> elements that don't have the LinkIt attributes.
    $xpath_query = "//a[@href != '']";
    foreach ($xpath->query($xpath_query) as $element) {
      /** @var \DOMElement $element */
      try {
        // Get the href value of the <a> element.
        $href = $element->getAttribute('href');
        // Strip off the scheme and host, so we only get the path.
        $domain = $this->request->getSchemeAndHttpHost() . $this->request->getBasePath();
        if (($position = strpos($href, $domain)) === 0) {
          $href = str_replace($domain, '', $href);
        }
        $target_type = $target_id = NULL;

        // Check if the href links to an entity.
        $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($href);
        if ($url && $url->isRouted() && preg_match('/^entity\./', $url->getRouteName())) {
          // Ge the target entity type and ID.
          $route_parameters = $url->getRouteParameters();
          $target_type = array_keys($route_parameters)[0];
          $target_id = $route_parameters[$target_type];
        }
        elseif (\preg_match('{^/?' . $this->publicFileDirectory . '/}', $href)) {
          // Check if we can map the link to a public file.
          $file_uri = preg_replace('{^/?' . $this->publicFileDirectory . '/}', 'public://', urldecode($href));
          $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $file_uri]);
          if ($files) {
            // File entity found.
            $target_type = 'file';
            $target_id = array_keys($files)[0];
          }
        }

        if ($target_type && $target_id) {
          $entity = $this->entityTypeManager->getStorage($target_type)->load($target_id);
          if ($entity) {

            if ($element->hasAttribute('data-entity-uuid')) {
              // Normally the Linkit plugin handles when a element has this
              // attribute, but sometimes users may change the HREF manually and
              // leave behind the wrong UUID.
              $data_uuid = $element->getAttribute('data-entity-uuid');
              // If the UUID is the same as found in HREF, then skip it because
              // it's LinkIt's job to register this usage.
              if ($data_uuid == $entity->uuid()) {
                continue;
              }
            }

            $entities[$entity->uuid()] = $target_type;
          }
        }
      }
      catch (\Exception $e) {
        // Do nothing.
      }
    }

    return $entities;
  }

}
