<?php

if (!defined('_PS_VERSION_'))
    exit;

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;


class ProductsModule extends Module implements WidgetInterface {

    private $templateFile;

    public function __construct()
    {
        $this->name = 'productsmodule';
        $this->tab = 'front_office_features';
        $this->version = '1.0';
        $this->author = 'Ściecha';
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];

        $this->bootstrap = true;
        parent::__construct();

        $this->need_instance = 0;

        $this->displayName = $this->l('Display Products Module');
        $this->description = $this->l('Choose category and display products module');

        $this->templateFile = 'module:productsmodule/views/templates/hook/productlist.tpl';

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('displayHome')) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        $output = '';
        $errors = array();

        if (Tools::isSubmit('submitCategory')) {
            $category = Tools::getValue('CATEGORY_PRODUCTLIST');
            if (!Validate::isInt($category) || $category <= 0) {
                $errors[] = $this->trans('The category ID is invalid. Please choose an existing category ID.', array(), 'Modules.Productsmodule.Admin');
            }

            if (isset($errors) && count($errors)) {
                $output = $this->displayError(implode('<br />', $errors));
            } else {
                Configuration::updateValue('CATEGORY_PRODUCTLIST', (int) $category);

                $output = $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
            }
        }

        return $output.$this->renderForm();
    }

    public function renderForm()
    {
        $categories = Category::getAllCategoriesName();

        $fields_form = array(
            'form' => array(
                'description' => $this->trans('Choose category to list products of.', array(), 'Modules.Productsmodule.Admin'),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Category:'),
                        'name' => 'CATEGORY_PRODUCTLIST',
                        'required' => true,
                        'options' => array(
                            'query' => $categories,
                            'id' => 'id_category',
                            'name' => 'name'
                        )
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCategory';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues()
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'CATEGORY_PRODUCTLIST' => Tools::getValue('CATEGORY_PRODUCTLIST', (int) Configuration::get('CATEGORY_PRODUCTLIST'))
        );
    }

    protected function getProducts($category)
    {
        $category = new Category((int) $category);

        $searchProvider = new \PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider(
            $this->context->getTranslator(),
            $category
        );

        $context = new \PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext($this->context);

        $query = new \PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery();
        $query->setSortOrder(\PrestaShop\PrestaShop\Core\Product\Search\SortOrder::random());

        $result = $searchProvider->runQuery(
            $context,
            $query
        );
        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever(
                $this->context->link
            ),
            $this->context->link,
            new \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
            new \PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $productsTpl = [];

        foreach ($result->getProducts() as $rawProduct) {
            $productsTpl[] = $presenter->present(
                $presentationSettings,
                $assembler->assembleProduct($rawProduct),
                $this->context->language
            );
        }

        return [
            'products' => $productsTpl,
            'category' => $category->getName()
        ];
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        $variables = $this->getWidgetVariables($hookName, $configuration);

        if (empty($variables)) {
             return false;
        }

        $this->smarty->assign($variables);

        return $this->fetch($this->templateFile);
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $data = $this->getProducts(Configuration::get('CATEGORY_PRODUCTLIST'));

        if (!empty($data)) {
            return $data;
        }
        return false;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || Configuration::deleteByName('CATEGORY_PRODUCTLIST')) {
            return false;
        }
        return true;
    }

}

