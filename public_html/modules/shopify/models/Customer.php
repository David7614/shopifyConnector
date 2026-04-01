<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use Exception;
use app\models\User;
use app\models\Customers;

class Customer
{
    private $customer;

    /**
     * @var User
     */
    private $user;

    public function __construct($customer, $user)
    {
        $this->customer = $customer;
        $this->user = $user;
    }

    private function getId()
    {
        $parts = explode('/', $this->customer['id']);
        return (string) end($parts);
    }

    private function getEmail()
    {
        if (!$this->customer['defaultEmailAddress'] || !$this->customer['defaultEmailAddress']['emailAddress']) {
            return '';
        }

        return $this->customer['defaultEmailAddress']['emailAddress'];
    }

    private function getFirstName()
    {
        $value = $this->customer['firstName'];

        if (!$value) {
            return '';
        }

        return $value;
    }

    private function getLastName()
    {
        $value = $this->customer['lastName'];

        if (!$value) {
            return '';
        }

        return $value;
    }

    private function getPhone()
    {
        if (!$this->customer['defaultPhoneNumber'] || !$this->customer['defaultPhoneNumber']['phoneNumber']) {
            return '';
        }

        return $this->customer['defaultPhoneNumber']['phoneNumber'];
    }

    private function getEmailApproval()
    {
        if (!$this->customer['defaultEmailAddress'] || !$this->customer['defaultEmailAddress']['marketingState']) {
            return 'never';
        }

        return $this->customer['defaultEmailAddress']['marketingState'] === 'SUBSCRIBED' ? 'every day' : 'never';
    }

    private function getSmsApproval()
    {
        if (!$this->customer['defaultPhoneNumber'] || !$this->customer['defaultPhoneNumber']['marketingState']) {
            return 'never';
        }

        return $this->customer['defaultPhoneNumber']['marketingState'] === 'SUBSCRIBED' ? 'every day' : 'never';
    }

    private function getNlfTime() 
    {
        if (!$this->customer['defaultEmailAddress'] || !$this->customer['defaultEmailAddress']['marketingUpdatedAt']) {
            return '2000-01-01 00:00:00';
        }

        return date('Y-m-d H:i:s', strtotime($this->customer['defaultEmailAddress']['marketingUpdatedAt']));
    }

    private function getZipCode()
    {
        if (!$this->customer['defaultAddress'] || !$this->customer['defaultAddress']['zip']) {
            return '';
        }

        return $this->customer['defaultAddress']['zip'];
    }

    private function getDataPermission()
    {
        return $this->getEmailApproval() === 'every day' ? 'full' : 'do_not_personalize';
    }

    private function getRegistration()
    {
        return $this->customer['createdAt'] ? 
            date('Y-m-d H:i:s', strtotime($this->customer['createdAt'])) : '2000-01-01 00:00:00';
    }

    private function getLastModificationDate()
    {
        return $this->customer['updatedAt'] ? 
            date('Y-m-d H:i:s', strtotime($this->customer['updatedAt'])) : '2000-01-01 00:00:00';
    }

    private function getParameterName($metafield)
    {
        if (!$metafield['definition'] || !$metafield['definition']['name']) {
            return  $metafield['key'];
        }
        
        return $metafield['definition']['name'];
    }

    private function getParameters()
    {
        if (!$this->customer['metafields'] && !$this->customer['metafields']['nodes']) {
            return serialize([]);
        }

        $parameters = [];

        foreach ($this->customer['metafields']['nodes'] as $metafield) {
            $parameters[] = [
                'NAME' => $this->getParameterName($metafield),
                'VALUE' => $metafield['value'],
            ];
        }

        return serialize($parameters);
    }

    public function prepareFromApi($force = false)
    {
        $hash = md5(serialize($this->customer));

        $customerModel = Customers::findOne(['customer_id' => $this->getId(), 'user_id' => $this->user->id]);

        if (!$customerModel) {
            $customerModel = new Customers();
        }

        $customerModel->customer_id = $this->getId();
        $customerModel->user_id = $this->user->id;

        $customerModel->login = '-';
        $customerModel->email = $this->getEmail();
        $customerModel->first_name = $this->getFirstName();
        $customerModel->lastname = $this->getLastName();
        $customerModel->phone = $this->getPhone();
        $customerModel->zip_code = $this->getZipCode();
        $customerModel->parameters = $this->getParameters();

        $customerModel->sms_frequency = $this->getSmsApproval();
        $customerModel->newsletter_frequency = $this->getEmailApproval();
        $customerModel->data_permission = $this->getDataPermission();

        $customerModel->page = 0;
        $customerModel->is_wholesaler = 0;

        $customerModel->nlf_time = $this->getNlfTime();
        $customerModel->tags = serialize([]);

        $customerModel->registration = $this->getRegistration();
        $customerModel->last_modification_date = $this->getLastModificationDate();

        $customerModel->data_hash = $hash;

        try {
            if ($customerModel->save()) {
                echo '>----- customer - prepareFromApi - Success' . PHP_EOL;
                return true;
            } else {
                echo '>----- customer - prepareFromApi - Error 1:' . PHP_EOL;
                var_dump($customerModel->getErrors());
            }
        } catch (Exception $e) {
            echo '>----- customer - prepareFromApi - Error 2:' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return false;
    }
}

