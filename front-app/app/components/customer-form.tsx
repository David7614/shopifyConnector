import { Button, FormLayout, Text, Box, Checkbox } from "@shopify/polaris";
import { Form } from "@remix-run/react";
import { useCallback, useEffect, useState } from "react";
import { ECustomerFields, EFieldType, FormNames, getFieldValue } from "app/utils/forms";

interface IProps {
  initFields: any;
  isSaved: boolean;
  onSave: () => void;
  onContinue: () => void;
}

export default function CustomerForm({ initFields, isSaved, onSave, onContinue }: IProps) {
  const [customerEmail, setCustomerEmail] = useState(false);
  const [customerRegistration, setCustomerRegistration] = useState(false);
  const [customerFirstName, setCustomerFirstName] = useState(false);
  const [customerLastName, setCustomerLastName] = useState(false);
  const [customerZipCode, setCustomerZipCode] = useState(false);
  const [customerPhone, setCustomerPhone] = useState(false);
  const [customerParameters, setCustomerParameters] = useState(false);

  const handleCustomerEmailChange = useCallback(
    (newChecked: boolean) => setCustomerEmail(newChecked),
    [],
  );

  const handleCustomerRegistrationChange = useCallback(
    (newChecked: boolean) => setCustomerRegistration(newChecked),
    [],
  );

  const handleCustomerFirstNameChange = useCallback(
    (newChecked: boolean) => setCustomerFirstName(newChecked),
    [],
  );

  const handleCustomerLastNameChange = useCallback(
    (newChecked: boolean) => setCustomerLastName(newChecked),
    [],
  );

  const handleCustomerZipCodeChange = useCallback(
    (newChecked: boolean) => setCustomerZipCode(newChecked),
    [],
  );

  const handleCustomerPhoneChange = useCallback(
    (newChecked: boolean) => setCustomerPhone(newChecked),
    [],
  );

  const handleCustomerParametersChange = useCallback(
    (newChecked: boolean) => setCustomerParameters(newChecked),
    [],
  );

  useEffect(() => {
    setCustomerEmail(getFieldValue(initFields, ECustomerFields.CUSTOMER_EMAIL, EFieldType.BOOL))
    setCustomerRegistration(getFieldValue(initFields, ECustomerFields.CUSTOMER_REGISTRATION, EFieldType.BOOL))
    setCustomerFirstName(getFieldValue(initFields, ECustomerFields.CUSTOMER_FIRST_NAME, EFieldType.BOOL))
    setCustomerLastName(getFieldValue(initFields, ECustomerFields.CUSTOMER_LAST_NAME, EFieldType.BOOL))
    setCustomerZipCode(getFieldValue(initFields, ECustomerFields.CUSTOMER_ZIP_CODE, EFieldType.BOOL))
    setCustomerPhone(getFieldValue(initFields, ECustomerFields.CUSTOMER_PHONE, EFieldType.BOOL))
    setCustomerParameters(getFieldValue(initFields, ECustomerFields.CUSTOMER_PARAMETERS, EFieldType.BOOL))
  }, [initFields])

  return (
    <Box padding="0">
      <div className="flex flex-col gap-4">
        <Text variant="headingXl" as="h4">
          Customer feed
        </Text>

        <Text as="p" tone="subdued" variant="bodyLg">
          Select which data should be exported in the customer feed.
        </Text>

        <div>
          <Form method="post">
            <FormLayout>
              <input name="formName" type="hidden" value={FormNames.CUSTOMER_FORM} />

              <div className="grid divide-y">
                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_EMAIL}
                    label="Customer email"
                    checked={customerEmail}
                    value={Number(customerEmail).toString()}
                    onChange={handleCustomerEmailChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_REGISTRATION}
                    label="Date of registration"
                    checked={customerRegistration}
                    value={Number(customerRegistration).toString()}
                    onChange={handleCustomerRegistrationChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_FIRST_NAME}
                    label="Customer first name"
                    checked={customerFirstName}
                    value={Number(customerFirstName).toString()}
                    onChange={handleCustomerFirstNameChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_LAST_NAME}
                    label="Customer last name"
                    checked={customerLastName}
                    value={Number(customerLastName).toString()}
                    onChange={handleCustomerLastNameChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_ZIP_CODE}
                    label="Customer zip code"
                    checked={customerZipCode}
                    value={Number(customerZipCode).toString()}
                    onChange={handleCustomerZipCodeChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_PHONE}
                    label="Customer phone number"
                    checked={customerPhone}
                    value={Number(customerPhone).toString()}
                    onChange={handleCustomerPhoneChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={ECustomerFields.CUSTOMER_PARAMETERS}
                    label="Customer parameters"
                    checked={customerParameters}
                    value={Number(customerParameters).toString()}
                    onChange={handleCustomerParametersChange}
                  />
                </div>
              </div>

              <Button 
                submit={isSaved ? true : false} 
                size="large" 
                variant={isSaved ? "primary" : "secondary"} 
                onClick={() => {
                  if (isSaved) {
                    onContinue()
                    return
                  }

                  if (!isSaved) {
                    onSave()
                  }
                }}
              >
                {isSaved ? 'Continue' : 'Save'}
              </Button>
            </FormLayout>
          </Form>
        </div>
      </div>
    </Box>
  );
}
