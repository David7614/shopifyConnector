import { Button, Text, Box } from "@shopify/polaris";
import { useCallback } from "react";

const getPixelSnippet = (trackpoint: any) => {
  return `
    const script = document.createElement('script');
    script.setAttribute('src', 'https://yottlyscript.com/script.js?tp=${trackpoint}');
    script.setAttribute('async', '');
    script.setAttribute('referrerpolicy', 'origin-when-cross-origin');
    document.head.appendChild(script);

    analytics.subscribe('page_viewed', (event) => {
      const customerId = init?.data?.customer?.id

      if (customerId) {
        window._yottlyOnload = window._yottlyOnload || [];
        _yottlyOnload.push(function() {
          diffAnalytics.customerLoggedIn(customerId)
        })
      }
    });

    analytics.subscribe('product_viewed', (event) => {
      window._yottlyOnload = window._yottlyOnload || [];
      _yottlyOnload.push(function() {
          diffAnalytics.productId(event.data.productVariant.product.id)
      });
    });

    analytics.subscribe('checkout_started', (event) => {
      const checkout = event.data.checkout;
      const orderItems = checkout.lineItems

      if (orderItems) {
        window._yottlyOnload = window._yottlyOnload || [];
        _yottlyOnload.push(function() {
          const products = [];

          orderItems.forEach((item) => {
            products.push({
              productId: item.variant.product.id,
              amount: item.quantity,
            })
          })

          diffAnalytics.cartInteraction({
            content: products,
          });
        })
      }
    });

    analytics.subscribe('checkout_completed', (event) => {
      const checkout = event.data.checkout;
      const orderItems = checkout.lineItems

      window._yottlyOnload = window._yottlyOnload || [];

      if (orderItems) {
        _yottlyOnload.push(function() {
          const products = [];

          orderItems.forEach((item) => {
            products.push({
              productId: item.variant.product.id,
              price: item.finalLinePrice.amount,
            })
          })

          diffAnalytics.order({ content: products });
        })
      }

      diffAnalytics.cartInteraction({ content: [] });
    });
  `;
};

interface IProps {
  trackpoint: string;
  appEmbedId?: string;
  onContinue: () => void;
}

export default function TrackingInstruction({ trackpoint, appEmbedId, onContinue }: IProps) {
  const onCopy = useCallback(async () => {
    try {
      if (!trackpoint) {
        shopify.toast.show("Copied failed: trackpoint cannot be empty", {
          isError: true,
        });
        return;
      }

      await navigator.clipboard.writeText(getPixelSnippet(trackpoint));

      shopify.toast.show("Copied to clipboard");
    } catch (e) {
      console.error("Copied failed", e);
      shopify.toast.show("Copied failed", {
        isError: true,
      });
    }
  }, [trackpoint]);

  return (
    <Box padding="0">
      <div className="flex flex-col gap-4">
        <Text variant="headingXl" as="h4">
          Tracking script configuration
        </Text>

        <Text variant="headingSm" as="p">
          1. Activate app embed by clicking on the link below:
        </Text>
        <Button
          url={`shopify:admin/themes/current/editor?context=apps&template=index&activateAppId=${appEmbedId}/app-embed`}
          target="_blank"
          variant="plain"
          disabled={!appEmbedId}
        >
          Activate app embed
        </Button>
        {!appEmbedId && (
          <div className="text-red-500">Problem with fetching app embed id</div>
        )}

        <Text variant="headingSm" as="p">
          2. Creating a custom pixel script
        </Text>
        <Text variant="bodyMd" as="p">
          - Copy the code snippet by clicking on the button below:
        </Text>
        <Button onClick={onCopy}>Copy snippet</Button>
        <Text variant="bodyMd" as="p">
          - Click on the link below and create a custom pixel, then paste the
          previously copied snippet
        </Text>
        <Button
          url={`shopify:admin/settings/customer_events`}
          target="_blank"
          variant="plain"
        >
          Add custom pixel
        </Button>
      </div>
      <div className="mt-4">
        <Button 
          size="large" 
          variant="primary"
          onClick={() => {
            onContinue()
          }}
        >
          Continue
        </Button>
      </div>
    </Box>
  );
}
