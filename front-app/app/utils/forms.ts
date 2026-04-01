export enum FormNames {
  SETTINGS_FORM = 'settingsForm',
  PRODUCT_FORM = 'productForm',
  CUSTOMER_FORM = 'customerForm',
}

export enum ESettingsFields {
  PRODUCT_FEED_ENABLED = 'product_feed_enabled',
  FEED_ENABLED = 'feed_enabled',
  EXPORT_TYPE = 'export_type',
  DATA_LANGUAGE = 'data_language',
  ORDERS_DATE_FROM = 'orders_date_from',
}

export enum EProductFields {
  PRODUCT_IMAGE = 'product_image',
  PRODUCT_DESCRIPTION = 'product_description',
  PRODUCT_BRAND = 'product_brand',
  PRODUCT_STOCK = 'product_stock',
  PRODUCT_PRICE_BEFORE_DISCOUNT = 'product_price_before_discount',
  PRODUCT_CATEGORY = 'product_category',
  PRODUCT_VARIANTS = 'product_variants',
  PRODUCT_PARAMETERS = 'product_parameters',
}

export enum ECustomerFields {
  CUSTOMER_EMAIL = 'customer_email',
  CUSTOMER_REGISTRATION = 'customer_registration',
  CUSTOMER_FIRST_NAME = 'customer_first_name',
  CUSTOMER_LAST_NAME = 'customer_last_name',
  CUSTOMER_ZIP_CODE = 'customer_zip_code',
  CUSTOMER_PHONE = 'customer_phone',
  CUSTOMER_PARAMETERS = 'customer_parameters',
 }

export const settingsFieldsToHandle = Object.values(ESettingsFields)
export const productFieldsToHandle = Object.values(EProductFields)
export const customerFieldsToHandle = Object.values(ECustomerFields)

export const fieldsToHandle = [...settingsFieldsToHandle, ...productFieldsToHandle, ...customerFieldsToHandle]

export enum EFieldType {
    BOOL = 'boolean',
    STRING = 'string',
}

export const getFieldValue = (fields: any[], key: string, type = EFieldType.STRING) => {
  if (!fields) {
    return null
  }

  const field: any = fields.find((field) => field.key === key)

  if (!field && type === EFieldType.BOOL) {
    return false
  }

  if (!field) {
    return null
  }

  if (type === EFieldType.BOOL) {
    return Boolean(Number(field.value || 0))
  }

  return field.value
}
