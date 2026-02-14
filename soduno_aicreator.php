<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Soduno_Aicreator extends Module
{
    const CONFIG_API_KEY = 'SODUNO_AICREATOR_API_KEY';
    const CONFIG_PROVIDER = 'SODUNO_AICREATOR_PROVIDER';

    /**
     * @var string[]
     */
    private $providers = ['gremini', 'chatgpt', 'freellm', 'claude', 'custmo'];

    public function __construct()
    {
        $this->name = 'soduno_aicreator';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Soduno';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Soduno AI Creator', [], 'Modules.Sodunoaicreator.Admin');
        $this->description = $this->trans('Base module scaffold for Soduno AI Creator.', [], 'Modules.Sodunoaicreator.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionAdminControllerSetMedia')
            && Configuration::updateValue(self::CONFIG_API_KEY, '')
            && Configuration::updateValue(self::CONFIG_PROVIDER, 'chatgpt');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CONFIG_API_KEY)
            && Configuration::deleteByName(self::CONFIG_PROVIDER);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitSodunoAiCreatorConfig')) {
            $apiKey = trim((string) Tools::getValue(self::CONFIG_API_KEY, ''));
            $provider = (string) Tools::getValue(self::CONFIG_PROVIDER, '');

            if (!in_array($provider, $this->providers, true)) {
                $provider = 'chatgpt';
            }

            Configuration::updateValue(self::CONFIG_API_KEY, $apiKey);
            Configuration::updateValue(self::CONFIG_PROVIDER, $provider);

            $output .= $this->displayConfirmation(
                $this->trans('Settings updated successfully.', [], 'Admin.Notifications.Success')
            );
        }

        return $output . $this->renderForm();
    }

    public function getPromptClient()
    {
        require_once __DIR__ . '/classes/SodunoAiPromptClient.php';

        return SodunoAiPromptClient::fromConfiguration();
    }

    public function hookActionAdminControllerSetMedia()
    {
        $controllerName = (string) Tools::getValue('controller');
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        $isProductPage = $controllerName === 'AdminProducts'
            || strpos($requestUri, '/sell/catalog/products/') !== false;

        if (!$isProductPage) {
            return;
        }

        $this->context->controller->addJS($this->_path . 'views/js/admin-ai-generate.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin-ai-generate.css');
    }

    private function renderForm()
    {
        $providerOptions = [];
        foreach ($this->providers as $provider) {
            $providerOptions[] = [
                'id_option' => $provider,
                'name' => $provider,
            ];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'password',
                        'name' => self::CONFIG_API_KEY,
                        'label' => $this->trans('API key', [], 'Admin.Global'),
                        'required' => false,
                        'desc' => $this->trans('Enter the API key for your AI model provider.', [], 'Modules.Sodunoaicreator.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'name' => self::CONFIG_PROVIDER,
                        'label' => $this->trans('Model provider', [], 'Modules.Sodunoaicreator.Admin'),
                        'options' => [
                            'query' => $providerOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                        'desc' => $this->trans('Choose the default provider/model.', [], 'Modules.Sodunoaicreator.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitSodunoAiCreatorConfig',
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSodunoAiCreatorConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->show_toolbar = false;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $currentProvider = (string) Configuration::get(self::CONFIG_PROVIDER);
        if (!in_array($currentProvider, $this->providers, true)) {
            $currentProvider = 'chatgpt';
        }

        $helper->fields_value = [
            self::CONFIG_API_KEY => (string) Configuration::get(self::CONFIG_API_KEY),
            self::CONFIG_PROVIDER => $currentProvider,
        ];

        return $helper->generateForm([$fieldsForm]);
    }
}
