import type { ActionFunctionArgs, LoaderFunctionArgs } from "@remix-run/node";
import { Page, Card, Box, Button, Text, InlineGrid, Link } from "@shopify/polaris";
import { useActionData, useFetcher, useLoaderData } from "@remix-run/react";
import { useCallback, useEffect, useState } from "react";
import { TitleBar } from "@shopify/app-bridge-react";
import { authenticate } from "../shopify.server";
import { getFeedOverview, getUser, handleUserAccess } from "app/models/user.server";
import { getConfig } from "app/models/user-config.server";
import { fieldsToHandle, FormNames } from "app/utils/forms";
import SettingsForm from "app/components/settings-form";
import FeedsTable from "app/components/feeds-table";
import ProductForm from "app/components/product-form";
import CustomerForm from "app/components/customer-form";
import TrackingInstruction from "app/components/tracking-instructions";
import settingsForm from "app/forms/settings-form";
import productForm from "app/forms/product-form";
import customerForm from "app/forms/customer-form";
import sambaLogo from "../images/sambalogo.png";

const METAFIELD_NAMESPACE = "tracking_scripts";
const METAFIELD_KEY = "trackpoint";
const METAFIELD_SMARTPOINT_KEY = "smartpoint";

enum ETab {
  GENERAL = "1-settings",
  PRODUCTS = "2-products",
  CUSTOMERS = "3-customers",
  TRACKING = "4-tracking",
  FEEDS = "5-feeds",
}

async function getTrackpoint(admin: any) {
  const GET_OWNER_ID = `
    query getOwnerID {
      currentAppInstallation {
        id
        metafield(namespace: "${METAFIELD_NAMESPACE}", key: "${METAFIELD_KEY}") {
          key
          value
        }
      }
    }
  `;

  const ownerResponse = await admin.graphql(GET_OWNER_ID);
  const ownerJson = await ownerResponse.json();

  return ownerJson.data.currentAppInstallation.metafield;
}

async function getSmartpoint(admin: any) {
  const GET_OWNER_ID = `
    query getOwnerID {
      currentAppInstallation {
        id
        metafield(namespace: "${METAFIELD_NAMESPACE}", key: "${METAFIELD_SMARTPOINT_KEY}") {
          key
          value
        }
      }
    }
  `;

  const ownerResponse = await admin.graphql(GET_OWNER_ID);
  const ownerJson = await ownerResponse.json();

  return ownerJson.data.currentAppInstallation.metafield;
}

export const loader = async ({ request }: LoaderFunctionArgs) => {
  const { admin, session } = await authenticate.admin(request);

  await handleUserAccess(session);

  const trackpoint = await getTrackpoint(admin);
  const smartpoint = await getSmartpoint(admin);

  try {
    const username = session.shop;

    const user = await getUser(username);

    if (!user) {
      throw new Error('User not exist')
    }

    const fields: any[] = []

    for (const key of fieldsToHandle) {
      const field = await getConfig(user.id, key)
      fields.push({ key, value: field?.value || null })
    }

    const feedOverview = await getFeedOverview(user.uuid)

    return {
      appEmbedId: process.env.APP_EMBED_ID || null,
      feedOverview,
      trackpoint: trackpoint?.value,
      smartpoint: smartpoint?.value || "1",
      fields,
    };
  } catch (e) {
    return {
      appEmbedId: null,
      feedOverview: null,
      trackpoint: null,
      smartpoint: "1",
      fields: [],
    };
  }
};

export const action = async ({ request }: ActionFunctionArgs) => {
  const formData = await request.formData();
  const formName = formData.get('formName');

  switch (formName) {
    case FormNames.SETTINGS_FORM:
      return settingsForm(request, formData)
    case FormNames.PRODUCT_FORM:
      return productForm(request, formData)
    case FormNames.CUSTOMER_FORM:
      return customerForm(request, formData)

    default:
      return null;
  }
};

export default function Index() {
  useFetcher<typeof loader>();

  const [initFields, setInitFields] = useState([])
  const [trackpoint, setTrackpoint] = useState("");
  const [smartpoint, setSmartpoint] = useState("0");
  const [feedOverview, setFeedOverview] = useState(undefined);
  const [appEmbedId, setAppEmbedId] = useState(undefined);
  const [isInitialPage, setIsInitialPage] = useState(true);

  const loaderData: any = useLoaderData<typeof loader>();
  const actionData = useActionData<typeof action>();

  const [isSaved, setIsSaved] = useState(false);

  const handleOnSave = () => {
    const currentIndex = tabs.findIndex((tab) => tab.id === selected)

    if (tabs.length < currentIndex) {
      return
    }

    setIsSaved(true)
  }

  const handleOnContinue = () => {
    const currentIndex = tabs.findIndex((tab) => tab.id === selected)

    if (tabs.length < currentIndex) {
      return
    }

    setSelected(tabs[currentIndex + 1].id)

    setIsSaved(false)
  }

  useEffect(() => {
    if (loaderData?.appEmbedId) {
      setAppEmbedId(loaderData.appEmbedId)
    }

    if (loaderData?.feedOverview) {
      setFeedOverview(loaderData.feedOverview)
    }

    if (loaderData?.trackpoint) {
      setTrackpoint(loaderData.trackpoint);
    }

    if (loaderData?.smartpoint) {
      setSmartpoint(loaderData.smartpoint);
    }

    setInitFields(loaderData.fields)
  }, [loaderData]);

  useEffect(() => {
    if (actionData?.status?.message) {
      shopify.toast.show(actionData.status.message, {
        isError: actionData.status.type !== "success",
      });
    }
  }, [actionData]);

  const [selected, setSelected] = useState(ETab.GENERAL);

  const handleTabChange = useCallback(
    (selectedTabIndex: any) => setSelected(selectedTabIndex),
    [],
  );

  const tabs = [
    {
      id: ETab.GENERAL,
      content: '1',
    },
    {
      id: ETab.PRODUCTS,
      content: '2',
    },
    {
      id: ETab.CUSTOMERS,
      content: '3',
    },
    {
      id: ETab.TRACKING,
      content: '4',
    },
    {
      id: ETab.FEEDS,
      content: '5',
    },
  ];

  return (
    <Page>
      <TitleBar title="Samba.ai"></TitleBar>
        <div className="flex justify-center">
          {isInitialPage && (
            <div className="max-w-[760px] w-full">
              <Card padding="0">
                <div className="welcome-container flex flex-col gap-8 p-10 max-sm:p-5">
                  <div className="logo flex justify-center">
                    <img src={sambaLogo} alt="Samba.ai logo" className="h-[28px] w-auto max-w-none object-contain" />
                  </div>

                  <div className="flex flex-col gap-3 text-center items-center">
                    <Text as="h1" variant="heading2xl">Welcome to Samba.ai Connector!</Text>

                    <Text as="p" variant="bodyLg" tone="subdued" fontWeight="medium">
                      <div className="max-w-[530px]">
                        We're glad you're here! The integration process is simple and will only take a few minutes. 
                        We will guide you step-by-step through the setup to connect your store with Samba.ai's powerful tools.
                      </div>
                    </Text>
                  </div>

                  <div className="steps-overview">
                    <div className="grid grid-cols-[1fr_1fr_1fr] max-md:grid-cols-[1fr_1fr] max-sm:grid-cols-[1fr] gap-5">
                      <div className=" bg-samba-light-blue rounded-[0.75rem] border-bg-[#e3e3e3] border">
                        <div className="flex flex-col gap-3 p-5">
                          <div className="flex gap-3 items-center">
                            <div className="step-number flex justify-center items-center w-10 h-10 rounded-full bg-samba-primary text-white font-semibold text-[14px] shrink-0">1-3</div>
                            <Text as="h3" variant="headingMd">
                              General Settings
                            </Text>
                          </div>
                          <Text as="p" variant="bodyMd" fontWeight="medium" tone="subdued">
                            First, we'll configure the basic rules for fetching data about your products, customers, and orders.
                          </Text>
                        </div>
                      </div>

                      <div className=" bg-samba-light-blue rounded-[0.75rem] border-bg-[#e3e3e3] border">
                        <div className="flex flex-col gap-3 p-5">
                          <div className="flex gap-3 items-center">
                            <div className="step-number flex justify-center items-center w-10 h-10 rounded-full bg-samba-primary text-white font-semibold text-[14px] shrink-0">4</div>
                            <Text as="h3" variant="headingMd">
                              Tracking Pixel
                            </Text>
                          </div>
                          <Text as="p" variant="bodyMd" fontWeight="medium" tone="subdued">
                            Next, we'll add a tracking pixel to your site. This is the heart of our analytics and smart automations.
                          </Text>
                        </div>
                      </div>

                      <div className="bg-samba-light-blue rounded-[0.75rem] border-bg-[#e3e3e3] border">
                        <div className="flex flex-col gap-3 p-5">
                          <div className="flex gap-3 items-center">
                            <div className="step-number flex justify-center items-center w-10 h-10 rounded-full bg-samba-primary text-white font-semibold text-[14px] shrink-0">5</div>
                            <Text as="h3" variant="headingMd">
                              Final Sync
                            </Text>
                          </div>
                          <Text as="p" variant="bodyMd" fontWeight="medium" tone="subdued">
                            Finally, we'll generate unique URL feeds for you to paste into the Samba.ai panel to complete the integration.
                          </Text>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex justify-center">
                    <button 
                      className="inline-block w-full max-w-[300px] px-6 py-[14px] text-[16px] font-semibold text-white bg-samba-primary hover:bg-[#e06014] border-none rounded-lg cursor-pointer no-underline transition-colors duration-200"
                      onClick={() => {
                        setIsInitialPage(false)
                      }}
                    >
                      Let's Get Started
                    </button>
                  </div>

                  <div className="bg-samba-light-blue rounded-[0.75rem] border-bg-[#e3e3e3] border">
                    <div className="text-center flex flex-col gap-2 p-5 items-center">
                      <Text as="h4" variant="headingMd">
                        Need any help?
                      </Text>

                      <Text as="p" variant="bodyLg" tone="subdued" fontWeight="medium">
                        <div className="max-w-[450px]">
                          Our team is here for you. If you run into any issues or have questions at any stage, please contact us:
                        </div>
                      </Text>

                      <div className="emails flex gap-1 justify-center">
                        <a href="mailto:support@samba.ai" className="font-medium text-samba-primary text-sm hover:underline">support@samba.ai</a> / 
                        <a href="mailto:opiekun@samba.ai" className="font-medium text-samba-primary text-sm hover:underline">opiekun@samba.ai</a>
                      </div>
                    </div>
                  </div>
                </div>
              </Card>
            </div>
          )}

          {!isInitialPage && (
            <div className="max-w-[560px] w-full">
              <Card padding="500">
                <div className="flex flex-col gap-[24px]">
                  <div>
                    <img src={sambaLogo} alt="Samba.ai logo" className="h-[28px] w-auto max-w-none object-contain" />
                  </div>

                  <Box padding="0">
                    <div className="min-h-[512px]">
                      {selected === ETab.GENERAL && (
                        <SettingsForm 
                          trackpointInitial={trackpoint} 
                          smartpointInitial={smartpoint} 
                          initFields={initFields} 
                          isSaved={isSaved} 
                          onSave={handleOnSave} 
                          onContinue={handleOnContinue} 
                        /> 
                      )}
                      {selected === ETab.PRODUCTS && (
                        <ProductForm 
                          initFields={initFields} 
                          isSaved={isSaved} 
                          onSave={handleOnSave} 
                          onContinue={handleOnContinue} 
                        />
                      )}
                      {selected === ETab.CUSTOMERS && (
                        <CustomerForm 
                          initFields={initFields} 
                          isSaved={isSaved} 
                          onSave={handleOnSave} 
                          onContinue={handleOnContinue} 
                        />
                      )}
                      {selected === ETab.TRACKING && (
                        <TrackingInstruction 
                          trackpoint={trackpoint} 
                          appEmbedId={appEmbedId} 
                          onContinue={handleOnContinue} 
                        />
                      )}
                      {selected === ETab.FEEDS && (
                        <FeedsTable feedOverview={feedOverview} />
                      )}
                    </div>
                  </Box>

                  <div className="flex justify-center">
                    <div className="flex gap-2">
                      {tabs.map((tab) => (
                        <Button 
                          size="large"
                          key={tab.id} 
                          pressed={selected == tab.id} 
                          onClick={() => {
                            handleTabChange(tab.id) 
                            setIsSaved(false)
                          }}
                        >
                          {tab.content}
                        </Button>
                      ))}
                    </div>
                  </div> 
                </div>
              </Card>
            </div>
          )}
        </div>
    </Page>
  );
}
