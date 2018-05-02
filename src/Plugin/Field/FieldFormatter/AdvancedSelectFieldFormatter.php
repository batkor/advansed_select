<?php

namespace Drupal\advanced_select\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'advanced_select_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "advanced_select_field_formatter",
 *   label = @Translation("Advanced select"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
class AdvancedSelectFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  protected $widgetSettings;
  protected $fieldOptions;

  /**
   * The entity manager service
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $entityFormDisplay;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('entity_form_display')
    );
  }

  /**
   * Construct a advanced_select_field_formatter object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ConfigEntityStorageInterface $entityFormDisplay) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityFormDisplay = $entityFormDisplay;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'image_style' => '',
        'image_class' => 'img',
        'value_class' => 'value',
        'value_tag' => 'p',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = image_style_options(FALSE);

    return [
        'image_style' => [
          '#title' => $this->t('Image style'),
          '#type' => 'select',
          '#default_value' => $this->getSetting('image_style'),
          '#empty_option' => $this->t('None (original image)'),
          '#options' => $image_styles,
        ],
        'image_class' => [
          '#title' => $this->t('Image class'),
          '#type' => 'textfield',
          '#default_value' => $this->getSetting('image_class'),
          '#description' => $this->t('Class for image wrapper'),
        ],
        'value_class' => [
          '#title' => $this->t('Value class'),
          '#type' => 'textfield',
          '#default_value' => $this->getSetting('value_class'),
          '#description' => $this->t('Class for value'),
        ],
        'value_tag' => [
          '#title' => $this->t('Value HTML tag'),
          '#type' => 'textfield',
          '#default_value' => $this->getSetting('value_tag'),
          '#description' => $this->t('HTML tag for value'),
        ],
      ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if (empty($this->getSetting('image_style'))) {
      $summary[] = $this->t('Original image');
    }
    else {
      $image_styles = image_style_options(FALSE);
      $summary[] = $this->t('Image style: @style', ['@style' => $image_styles[$this->getSetting('image_style')]]);
    }

    return $summary;
  }

  public function setWidgetWettings(FieldItemListInterface $items) {

    $field_name = $items->getName();
    $field_entity_type_id = $items->getEntity()->getEntityTypeId();
    $field_entity_bundle = $items->getEntity()->bundle();
    $form_display = $this->entityFormDisplay->load($field_entity_type_id . '.' . $field_entity_bundle . '.default');
    $this->widgetSettings = $form_display->getComponent($field_name)['settings'];
  }

  public function setFieldOptions($items) {
    $provider = $items->getFieldDefinition()
                      ->getFieldStorageDefinition()
                      ->getOptionsProvider('value', $items->getEntity());
    $this->fieldOptions = OptGroup::flattenOptions($provider->getPossibleOptions());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $this->setWidgetWettings($items);
    $this->setFieldOptions($items);

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   */
  protected function viewValue(FieldItemInterface $item) {

    $widgetSettings = $this->widgetSettings['values'];
    $value = $item->value;
    $options = $this->fieldOptions;

    // render img
    if (!empty($widgetSettings[$value]['img']['fids'])) {
      $file = File::load($widgetSettings[$value]['img']['fids']);
      $class_image = $this->getSetting('image_class');
      if (empty($this->getSetting('image_style'))) {
        $render = [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#prefix' => '<div class="' . $class_image . '">',
          '#suffix' => '</div>',
        ];
      }
      else {
        $render = [
          '#theme' => 'image_style',
          '#style_name' => $this->getSetting('image_style'),
          '#uri' => $file->getFileUri(),
          '#prefix' => '<div class="' . $class_image . '">',
          '#suffix' => '</div>',
        ];
      }

      $output[] = $render;
    }

    // render label

    $label = isset($options[$value]) ? $options[$value] : $value;
    $output[] = [
      '#type' => 'inline_template',
      '#template' => "<{{tag}} class='{{class}}'>{{label}}</{{tag}}>",
      '#context' => [
        'label' => $label,
        'tag' => $this->getSetting('value_tag'),
        'class' => $this->getSetting('value_class'),
      ],
    ];

    return $output;
  }

}
