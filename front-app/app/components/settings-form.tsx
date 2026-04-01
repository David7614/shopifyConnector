import {
  Button,
  FormLayout,
  TextField,
  Text,
  Box,
  Tooltip,
  Icon,
  Select,
  List,
} from "@shopify/polaris";

import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

import {
  Button as ShButton,
} from "@/components/ui/button"

import { QuestionCircleIcon, CalendarIcon } from "@shopify/polaris-icons";
import { useState, useCallback, useEffect } from "react";
import { Form } from "@remix-run/react";
import { ESettingsFields, FormNames, getFieldValue } from "app/utils/forms";
import DatePickerInput from "./date-picker-input";
import { CustomModal } from "./custom-modal";
import smartpointGif from "../images/smartpoint.gif";
import { HintIcon } from "./hint-icon";
// import { CalendarIcon } from "lucide-react";
import { Calendar } from "./ui/calendar";
import { cn } from "@/lib/utils";
import { format } from "date-fns"

const feedEnabledOptions = [
  { label: "Feed enabled", value: "1" },
  { label: "Feed disabled", value: "0" },
];

const exportTypeOptions = [
  { label: "Full database", value: "0" },
  { label: "Incremental (recommended)", value: "1" },
];

const smartpointOptions = [
  { label: "Smartpoint included", value: "1" },
  { label: "Smartpoint from GTM", value: "0" },
];

const dataLanguageOptions = [
  { label: "English", value: "en" },
  { label: "Polish", value: "pl" },
];

interface IProps {
  trackpointInitial: any;
  smartpointInitial: any;
  initFields: any;
  isSaved: boolean;
  onSave: () => void;
  onContinue: () => void;
}

export default function SettingsForm({ trackpointInitial, smartpointInitial, initFields, isSaved, onSave, onContinue }: IProps) {
  const [feedEnabled, setFeedEnabled] = useState("1");
  const [exportType, setExportType] = useState("0");
  const [trackpoint, setTrackpoint] = useState("");
  const [smartpoint, setSmartpoint] = useState("1");
  const [dataLanguage, setDataLanguage] = useState("en");
  const [ordersDateFrom, setOrdersDateFrom] = useState<any>(new Date())

  useEffect(() => {
    setTrackpoint(trackpointInitial)
  }, [trackpointInitial])

  useEffect(() => {
    setSmartpoint(smartpointInitial)
  }, [smartpointInitial])

  useEffect(() => {
    console.log(">--------- initFields:")
    console.log(initFields)

    setFeedEnabled(getFieldValue(initFields, ESettingsFields.FEED_ENABLED))
    setExportType(getFieldValue(initFields, ESettingsFields.EXPORT_TYPE))
    setDataLanguage(getFieldValue(initFields, ESettingsFields.DATA_LANGUAGE))
    const ordersDateFrom = getFieldValue(initFields, ESettingsFields.ORDERS_DATE_FROM)
    setOrdersDateFrom(ordersDateFrom ? new Date(ordersDateFrom) : new Date())
  }, [initFields])

  const handleFeedEnabledChange = useCallback(
    (value: string) => setFeedEnabled(value),
    [],
  );

  const handleExportTypeChange = useCallback(
    (value: string) => setExportType(value),
    [],
  );

  const handleTrackpointChange = useCallback(
    (value: string) => setTrackpoint(value),
    [],
  );

  const handleSmartpointChange = useCallback(
    (value: string) => setSmartpoint(value),
    [],
  );

  const handleDataLanguageChange = useCallback(
    (value: string) => setDataLanguage(value),
    [],
  );

  return (
    <Box padding="0">
      <div className="flex flex-col gap-4">
        <Text variant="headingXl" as="h4">
          Samba settings
        </Text>

        <Text as="p" tone="subdued" variant="bodyLg">
          Configure the integration with Samba.ai. Enter the required data and
          select the appropriate options.
        </Text>

        <div>
          <Form method="post">
            {/* <FormLayout> */}
            <div className="flex flex-col gap-4">
              <input name="formName" type="hidden" value={FormNames.SETTINGS_FORM} />

              <div>
                <div className="Polaris-Labelled__LabelWrapper">
                  <div className="flex gap-1 items-center">
                    <div className="Polaris-Label">
                      <label
                        id=":trackpoint:Label"
                        className="Polaris-Label__Text"
                      >
                        <span className="Polaris-Text--root Polaris-Text--bodyMd">
                          Trackpoint
                        </span>
                      </label>
                    </div>

                    <CustomModal 
                      id="trackpoint-id-hint-modal"
                      title="What is a Trackpoint ID and where to find it?"
                      content={(
                        <>
                          <Text as="p" variant="bodyMd">Your Trackpoint ID is a unique identifier for your online store within Samba.ai. It enables our scripts to track customer behavior in real-time.</Text>
                          <br />
                          <Text as="p" variant="bodyMd">You can find your Trackpoint ID in the Samba.ai application by following these steps:</Text>
                          <br />
                          <List type="number">
                            <List.Item>Navigate to <strong>Eshop Settings</strong> → <strong>Overview</strong>.</List.Item>
                            <List.Item>Look for the <strong>Eshop Account</strong> section.</List.Item>
                            <List.Item>Copy your ID from there.</List.Item>
                          </List>
                          <img src={smartpointGif} alt="Instructional GIF showing where to find the Trackpoint ID in Samba.ai interface" className="rounded-lg mt-4 border border-samba-border-gray"></img>
                        </>
                      )}
                      children={(
                        <HintIcon className="!text-samba-primary" />
                      )}
                    />
                  </div>
                </div>

                <TextField
                  name="trackpoint"
                  label="Trackpoint"
                  labelHidden
                  autoComplete="off"
                  value={trackpoint}
                  onChange={handleTrackpointChange}
                />
              </div>

              <div>
                <div className="Polaris-Labelled__LabelWrapper">
                  <div className="flex gap-1 items-center">
                    <div className="Polaris-Label">
                      <label id=":trackpoint:Label" className="Polaris-Label__Text">
                        <span className="Polaris-Text--root Polaris-Text--bodyMd">
                          Smartpoint
                        </span>
                      </label>
                    </div>

                    <Tooltip 
                      content={(
                        <div className="flex flex-col gap-2">
                          <div><b>Smartpoint included</b> - Tracking scripts will be executed from samba application.</div>
                          <hr />
                          <div><b>Smartpoint from GTM</b> - Independently integrate tracking scripts through GTM.</div>
                        </div>
                      )}
                      width="wide"
                    >
                      <HintIcon className="!text-samba-primary" />
                    </Tooltip>
                  </div>
                </div>

                <Select
                  name="smartpoint"
                  label="Smartpoint"
                  labelHidden
                  options={smartpointOptions}
                  onChange={handleSmartpointChange}
                  value={smartpoint}
                />
              </div>

              <Select
                name={ESettingsFields.FEED_ENABLED}
                label="Would you like to use our feed?"
                options={feedEnabledOptions}
                onChange={handleFeedEnabledChange}
                value={feedEnabled}
              />

              <Select
                name={ESettingsFields.DATA_LANGUAGE}
                label="In which language should the data be returned?"
                options={dataLanguageOptions}
                onChange={handleDataLanguageChange}
                value={dataLanguage}
              />

              <div className="hidden">
                <DatePickerInput 
                  name={ESettingsFields.ORDERS_DATE_FROM} 
                  value={ordersDateFrom} 
                  onChange={() => {}} 
                />
              </div>

              {/* <TextField
                label="Store name"
                labelHidden
                value={ordersDateFrom} 
                // onChange={handleChange}
                autoComplete="off"
              /> */}

              {/* <input name={ESettingsFields.ORDERS_DATE_FROM} type="hidden" value={ESettingsFields.ORDERS_DATE_FROM} /> */}

              <div>
                <div className="Polaris-Labelled__LabelWrapper">
                  <div className="flex gap-1 items-center">
                    <div className="Polaris-Label">
                      <label id=":trackpoint:Label" className="Polaris-Label__Text">
                        <span className="Polaris-Text--root Polaris-Text--bodyMd">
                          Generate order data from
                        </span>
                      </label>
                    </div>
                  </div>
                </div>

                <Popover>
                  <PopoverTrigger asChild>
                      <ShButton
                        variant="outline"
                        className={cn(
                          "w-[240px] pl-3 text-left font-normal",
                          "border border-[0.04125rem] border-[rgba(138,138,138,1)] border-t-[#898f94] rounded-[0.5rem] h-[32px] p-0  px-[0.5rem] flex justify-start gap-[0.25rem] [font-family:var(--p-font-family-sans)] [font-size:var(--p-font-size-325)] [font-weight:var(--p-font-weight-regular)] [line-height:var(--p-font-line-height-500)] [letter-spacing:initial]"
                        )}
                      >
                        <CalendarIcon className="opacity-50 !h-[20px] !w-[20px]" />
                        {ordersDateFrom ? (
                          format(ordersDateFrom, "PPP")
                        ) : (
                          <span>Pick a date</span>
                        )}
                      </ShButton>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0 rounded-xl border-none" align="start">
                    <Calendar
                      mode="single"
                      selected={ordersDateFrom}
                      // selected={new Date(ordersDateFrom)}
                      // defaultMonth={new Date(ordersDateFrom)}
                      defaultMonth={ordersDateFrom}
                      // onSelect={(data: any) => setOrdersDateFrom(data)}
                      onSelect={(data: any) => {
                        setOrdersDateFrom(data)
                        // setOrdersDateFrom(new Date(data).toISOString().slice(0, 10))
                      }}
                      disabled={(date) =>
                        date > new Date() || date < new Date("1900-01-01")
                      }
                      captionLayout="dropdown"
                      className="rounded-xl border border-[#C1C1C1]"
                    />
                  </PopoverContent>
                </Popover>
              </div>

              <Select
                name={ESettingsFields.EXPORT_TYPE}
                label="Data export type"
                options={exportTypeOptions}
                onChange={handleExportTypeChange}
                value={exportType}
              />

              <div>
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
              </div>
            </div>
            {/* </FormLayout> */}
          </Form>
        </div>
      </div>
    </Box>
  );
}

