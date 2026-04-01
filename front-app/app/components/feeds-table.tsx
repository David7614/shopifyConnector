import type { TableData } from "@shopify/polaris";
import { Button, Text, Box, DataTable } from "@shopify/polaris";
import { useCallback, useEffect, useState } from "react";
import { CustomModal } from "./custom-modal";
import sambaFeedsGif from "../images/samba-feeds.gif";

enum EFeedType {
  PRODUCT = 'products',
  CUSTOMER = 'customers',
  ORDER = 'orders',
}

interface IProps {
  feedOverview?: any;
}

export default function FeedsTable({ feedOverview }: IProps) {
  const [tableRows, setTableRows] = useState<TableData[][]>([]);

  const onCopy = useCallback(async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);

      shopify.toast.show("Copied to clipboard");
    } catch (e) {
      console.error("Copied failed", e);
      shopify.toast.show("Copied failed", {
        isError: true,
      });
    }
  }, []);

  useEffect(() => {
    const rows = [];

    if (feedOverview?.[EFeedType.PRODUCT]) {
      rows.push([
        'Products', 
        <Button key="products" onClick={() => onCopy(feedOverview?.[EFeedType.PRODUCT].url)}>Copy link</Button>, 
        feedOverview?.[EFeedType.PRODUCT].all, 
        `${feedOverview?.[EFeedType.PRODUCT].status} ${feedOverview?.[EFeedType.PRODUCT].current}`, 
      ])
    }

    if (feedOverview?.[EFeedType.ORDER]) {
      rows.push([
        'Orders', 
        <Button key="orders" onClick={() => onCopy(feedOverview?.[EFeedType.ORDER].url)}>Copy link</Button>, 
        feedOverview?.[EFeedType.ORDER].all, 
        `${feedOverview?.[EFeedType.ORDER].status} ${feedOverview?.[EFeedType.ORDER].current}`, 
      ])
    }

    if (feedOverview?.[EFeedType.CUSTOMER]) {
      rows.push([
        'Customers', 
        <Button key="customers" onClick={() => onCopy(feedOverview?.[EFeedType.CUSTOMER].url)}>Copy link</Button>, 
        feedOverview?.[EFeedType.CUSTOMER].all, 
        `${feedOverview?.[EFeedType.CUSTOMER].status} ${feedOverview?.[EFeedType.CUSTOMER].current}`, 
      ])
    }

    setTableRows(rows)
  }, [feedOverview, onCopy])

  return (
    <Box padding="0">
      <div className="flex flex-col gap-4">
        <div className="final-step-banner flex items-start bg-[#e8f6fe] border-l-4 border-[#8ed1fc] rounded-lg p-4">
            <div className="final-step-text text-[15px] leading-[1.6] text-[#0b1d4c] font-normal">
                <strong className="text-[#ff8326] text-[16px] block mb-1">This is the final step!</strong>
                <span>Configuration is complete. Now, just copy each link from the table below and paste it into the appropriate place in the Samba.ai application. </span> 
                <CustomModal 
                  id="feeds-instruction-modal"
                  title="Connecting Your Data Feeds to Samba.ai"
                  content={(
                    <>
                      <Text as="p" variant="bodyMd" tone="subdued">
                        To finalize the integration, you need to synchronize your store's data with Samba.ai. This is done by linking your:
                      </Text>
                      <Text as="p" tone="subdued"> 
                        <strong> Customer feed <br /> Product feed </strong>and <br /><strong>Order feed <br /></strong><br />
                      </Text>
                      <Text as="p" variant="bodyMd" tone="subdued">
                        Please copy each feed URL from the table and paste it into the corresponding field within the Samba.ai application.
                      </Text>
                      <Text as="p" variant="bodyMd" tone="subdued">
                        The short animation below demonstrates this process step-by-step.
                      </Text>
                      <img src={sambaFeedsGif} alt="Samba.ai Demo GIF" className="rounded-lg mt-4 border border-samba-border-gray"></img>
                    </>
                  )}
                  children={(
                    <span className="inline-block mt-2 text-[#ff8326] font-medium underline cursor-pointer">Click here for detailed instruction</span>
                  )}
                />
            </div>
        </div>

        <Text variant="headingXl" as="h4">
          Generated feeds
        </Text>

        <Text as="p" tone="subdued" variant="bodyLg">
          Copy each URL and place it directly into samba application in the appropriate place.
        </Text>

        <DataTable
          columnContentTypes={[
            'text',
            'text',
            'text',
            'text',
          ]}
          headings={[
            'Feed name',
            'URL',
            'Data (total)',
            'Last sync.',
          ]}
          rows={tableRows}
        />
      </div>
    </Box>
  );
}
