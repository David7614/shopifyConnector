<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use app\models\Customers;
use SimpleXMLElement;
use app\models\User;
use app\modules\xml_generator\helper\SambaHelper;
use Exception;

class CustomerXml
{
    /**
     * @param Customers $customer
     * @param User $user
     */
    public static function getEntity($customer, $user)
    {
        $fields_to_integrate = self::getFieldsToIntegrate($user);

        try {
            $customersXml = new SimpleXMLElement('<CUSTOMERS/>');
            $customerXml = $customersXml->addChild('CUSTOMER');

            $customerXml->addChild('CUSTOMER_ID', $customer['customer_id']);

            if (in_array('email', $fields_to_integrate)) {
                $customerXml->addChild('EMAIL', $customer['email']);
            }

            if (in_array('customer_registration', $fields_to_integrate)) {
                $customerXml->addChild('REGISTRATION', SambaHelper::getCorrectSambaDate($customer['registration']));
            }

            if (in_array('customer_first_name', $fields_to_integrate) && !empty($customer['first_name'])) {
                $customerXml->addChild('FIRST_NAME', $customer['first_name']);
            }

            if (in_array('customer_last_name', $fields_to_integrate) && !empty($customer['lastname'])) {
                $customerXml->addChild('LAST_NAME', $customer['lastname']);
            }

            if (in_array('customer_zip_code', $fields_to_integrate) && !empty($customer['zip_code'])) {
                $customerXml->addChild('ZIP_CODE', $customer['zip_code']);
            }

            if (in_array('customer_phone', $fields_to_integrate) && !empty($customer['phone'])) {
                // $phone = preg_replace("/[^0-9]/", "", $customer['phone']);
                // $custChild->addChild('PHONE', $phone);
                $customerXml->addChild('PHONE', $customer['phone']);
            }

            if (in_array('customer_parameters', $fields_to_integrate)) {
                $parameters = unserialize($customer->parameters);

                if (!empty($parameters)) {
                    $parametersXml = $customerXml->addChild('PARAMETERS');

                    foreach ($parameters as $parameter) {
                        $parameterXml = $parametersXml->addChild('PARAMETER');

                        foreach ($parameter as $index => $value){
                            $parameterXml->addChild($index, $value);
                        }
                    }
                }
            }

            $customerXml->addChild('DATA_PERMISSION', $customer['data_permission']);

            if ($customer['newsletter_frequency'] !== null && $customer['newsletter_frequency'] !== 'never') {
                $nlf_time = $customer['nlf_time'];

                if ($customer['nlf_time'] === '2000-01-01 00:00:00') {
                    $nlf_time = $customer['registration'];
                }

                $customerXml->addChild('NLF_TIME', SambaHelper::getCorrectSambaDate($nlf_time));
            }

            $customerXml->addChild('NEWSLETTER_FREQUENCY', $customer['newsletter_frequency']);
            $customerXml->addChild('SMS_FREQUENCY', $customer['sms_frequency']);

            return $customerXml->asXml();
        } catch(Exception $e) {
            echo '>----- customer - getEntity - Error' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return null;
    } 

    public static function getFieldsToIntegrate($user)
    {
        $fields_to_integrate = [];

        if ($user->config->get('customer_registration')) {
            $fields_to_integrate[] = 'customer_registration';
        }

        if ($user->config->get('customer_first_name')) {
            $fields_to_integrate[] = 'customer_first_name';
        }

        if ($user->config->get('customer_last_name')) {
            $fields_to_integrate[] = 'customer_last_name';
        }

        if ($user->config->get('customer_zip_code')) {
            $fields_to_integrate[] = 'customer_zip_code';
        }

        if ($user->config->get('customer_phone')) {
            $fields_to_integrate[] = 'customer_phone';
        }

        if ($user->config->get('customer_email')) {
            $fields_to_integrate[] = 'email';
        }

        if ($user->config->get('customer_parameters')) {
            $fields_to_integrate[] = 'customer_parameters';
        }

        return $fields_to_integrate;
    }
}
