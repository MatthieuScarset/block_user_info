<?php

namespace Drupal\block_user\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->userViewBuilder = $this->entityTypeManager->getViewBuilder('user');
    
    // Get user info.
    $this->currentAccount = $current_user;
    $account = $this->currentAccount;
    $uid = $account->id();
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($uid);

    // Get user entity display mode.
    $this->defaultViewMode = 'full';
    $view_modes = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties(['targetEntityType' => 'user']);
    $this->userViewModes = [];
    foreach ($view_modes as $key => $viewmode) {
      $machine_name = $viewmode->get('mode');
      $this->userViewModes[$machine_name] = $machine_name;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $uid = isset($this->configuration['user']) ? $this->configuration['user'] : $this->currentUser;
    $targeted_user = $this->entityTypeManager->getStorage('user')->load($uid);
    $description = $this->t('Select which display mode this block should use.');
    $help = $this->t('You can <a href="/admin/structure/display-modes/view/add/user">add a new display mode here</a> and <a href="/admin/config/people/accounts/display">edit displayed fields there</a>.');

    $form['userinfo'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    );
    $form['userinfo']['view_mode'] = array(
      '#type' => 'select',
      '#options' => $this->userViewModes,
      '#title' => $this->t('User display mode'),
      '#description' => $description . '<br />' . $help,
      '#default_value' => isset($this->configuration['view_mode']) ? $this->configuration['view_mode'] : $this->defaultViewMode,
    );
    
    $form['userinfo']['target'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t("Select a specific user."),
      '#description' => $this->t("By default, the block displays the current user's info."),
      '#default_value' => isset($this->configuration['target']) ? $this->configuration['target'] : FALSE,
    );
    $form['userinfo']['user'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],      
      '#title' => $this->t('Targeted user'),
      '#description' => $this->t('Select which user this block should display.'),
      '#default_value' => $targeted_user ? $targeted_user : NULL,
      '#states' => array(
        'visible' => array(
          ':input[name="settings[userinfo][target]"]' => array('checked' => TRUE),
        ),
        'required' => array(
          ':input[name="settings[userinfo][target]"]' => array('checked' => TRUE),
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
    foreach ($userinfo as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->configuration['target']) {
      $user = $this->entityTypeManager->getStorage('user')->load($this->configuration['user']);
    }
    else {
      $user = $this->currentUser;
    }
    $view_mode = isset($this->configuration['view_mode']) ? $this->configuration['view_mode'] : $this->defaultViewMode;
    $build = $this->userViewBuilder->view($user, $view_mode);
    return $build;
  }

}
