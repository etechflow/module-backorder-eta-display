<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Block\Adminhtml\License;

use ETechFlow\BackorderEtaDisplay\Model\LicenseValidator;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Gate extends Template
{
    public function __construct(
        Context $context,
        private readonly LicenseValidator $licenseValidator,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormKey(): string
    {
        if ($this->formKey !== null) {
            return $this->formKey->getFormKey();
        }
        return \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Data\Form\FormKey::class)
            ->getFormKey();
    }

    public function getConfigUrl(): string
    {
        return (string) $this->getUrl(
            'adminhtml/system_config/edit',
            ['section' => 'etechflow_backorderetadisplay', '_fragment' => 'etechflow_backorderetadisplay_license-head']
        );
    }

    public function getCheckoutUrl(): string
    {
        return (string) $this->getUrl('etechflow_backorderetadisplay/license/checkout');
    }

    public function getCurrentDomain(): string
    {
        return $this->licenseValidator->getCurrentHost();
    }

    public function isStripeConfigured(): bool
    {
        $sk = trim((string) $this->_scopeConfig->getValue('etechflow_backorderetadisplay/payment/stripe_secret_key'));
        return $sk !== '';
    }

    public function isPortalConfigured(): bool
    {
        $u = trim((string) $this->_scopeConfig->getValue('etechflow_backorderetadisplay/license/portal_url'))
           ?: trim((string) $this->_scopeConfig->getValue('etechflow_backorderetadisplay/license/portal_api_url'));
        return $u !== '';
    }
}
