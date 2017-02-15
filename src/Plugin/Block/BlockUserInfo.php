<?php

namespace Drupal\block_user\Plugin\Block;

use Drupal\Core\Link;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display 'Site branding' elements.
 *
 * @Block(
 *   id = "block_user_info",
 *   admin_label = @Translation("User Info")
 * )
 */
class BLockUserInfo extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The targeted user.
   *
   * @var string|array
   */
  protected $user;

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Creates a BLockUserInfo instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManager $entityTypeManager,
    AccountProxyInterface $current_account
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
    $this->userViewBuilder = $this->entityTypeManager->getViewBuilder('user');

    // Get user info.
    $this->currentAccount = $current_account;
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentAccount->id());

    // Get user entity display mode.
    $this->defaultViewMode = 'default';
    $view_modes = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties(['targetEntityType' => 'user']);
    $this->userViewModes = [];
    foreach ($view_modes as $viewmode) {
      $machine_name = $viewmode->get('mode');
      $this->userViewModes[$machine_name] = $machine_name;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => TRUE,
      'target' => 'current',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    
    // Prepare form texts.
    $url_add_view_mode = Link::createFromRoute(
      $this->t('add a new view mode here'),
      'entity.entity_view_mode.add_form',
      ['entity_type_id' => 'user']
    );
    $url_account_display = Link::createFromRoute(
      $this->t('edit displayed fields there'),
      'entity.entity_view_display.user.default'
    );
    $description = $this->t('Select which display mode this block should use.');
    $help = $this->t('You can ') . $url_add_view_mode->toString() . ' ' . $this->t('and') . ' ' . $url_account_display->toString();
    
    // Load referenced users entities.
    $user_default_value = $this->getReferencedUsers();

    // Build form.
    $form['userinfo'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    );
    $form['userinfo']['view_mode'] = array(
      '#type' => 'select',
      '#options' => $this->userViewModes,
      '#title' => $this->t('Select a display mode'),
      '#description' => $description . '<br />' . $help,
      '#default_value' => isset($this->configuration['view_mode']) ? $this->configuration['view_mode'] : $this->defaultViewMode,
    );
    $form['userinfo']['target'] = array(
      '#type' => 'radios',
      '#options' => [
        'current' => 'Current user',
        'author' => 'Node author',
        'users' => 'Specific user(s)',
      ],
      '#title' => $this->t("Select user(s) to be retrieved"),
      '#default_value' => isset($this->configuration['target']) ? $this->configuration['target'] : FALSE,
    );
    $form['userinfo']['user'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#default_value' => $user_default_value ? $user_default_value : NULL,
      '#tags' => TRUE,
      '#title' => $this->t('Targeted user'),
      '#description' => $this->t("Select which user(s) profile this block should display."),
      '#states' => array(
        'visible' => array(
          array(
            array(':input[name="settings[userinfo][target]"]' => array('value' => 'users')),
            'or',
            array(':input[name="settings[userinfo][target]"]' => array('value' => 'multiple')),
          ),
        ),
        'required' => array(
          array(
            array(':input[name="settings[userinfo][target]"]' => array('value' => 'users')),
            'or',
            array(':input[name="settings[userinfo][target]"]' => array('value' => 'multiple')),
          ),
        ),
      ),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $userinfo = $form_state->getValue('userinfo');
    // Empty the entity_autcomplete field.
    if (isset($userinfo['target']) && $userinfo['target'] != 'users') {
      $userinfo['user'] = NULL;
    }
    // Save configurations keys/values.
    foreach ($userinfo as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $users = $this->getReferencedUsers();
    $view_mode = isset($this->configuration['view_mode']) ? $this->configuration['view_mode'] : $this->defaultViewMode;
    foreach ($users as $uid => $user) {
      $build[] = $this->userViewBuilder->view($user, $view_mode);
    }
    return $build;
  }

  /**
   * Load referenced users.
   *
   * @return bool|array
   *   An array of loaded user entities.
   */
  protected function getReferencedUsers() {
    $uids = [];
    if (!isset($this->configuration['user'])) {
      $uids[] = (int) $this->currentUser->id();
    }
    else {
      foreach ($this->configuration['user'] as $ref) {
        if (isset($ref['target_id'])) {
          $uids[] = (int) $ref['target_id'];
        }
      }
    }
    return $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
  }

}
