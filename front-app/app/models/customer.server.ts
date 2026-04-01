import type { customers as TCustomer, orders as TOrder } from '@prisma/client';
import { unserialize } from 'php-serialize'
import prisma from "../db.server";

export const getCustomer = async (email: string) => {
    return await prisma.customers.findFirst({
        where: {
            email,
        },
    });
};

export const getOrders = async (ids: number[]) => {
    return await prisma.orders.findMany({
        where: {
            id: { in: ids },
        }
    });
}

type TCustomerDataResult = {
  customer?: TCustomer;
  orders?: TOrder[];
};

const getOrderObjects = (orders: TOrder[]) => {
    return orders.map((order) => {
        const object: any = {
            id: order.order_id,
            customer: {
                id: order.customer_id,
            },
            email: order.email,
            billingAddress: {
                phone: order.phone,
                zip: order.zip_code,
                countryCodeV2: order.country_code,
            },
        };

        const defaultDate = new Date("2000-01-01T00:00:00");

        if (order?.created_on && order.created_on !== defaultDate) {
            object.createdAt = order.created_on.toDateString();
        }

        if (order?.finished_on && order.finished_on !== defaultDate) {
            object.closedAt = order.finished_on.toDateString();
        }

        const positions = unserialize(order.order_positions);

        if (Array.isArray(positions)) {
            const lineItems: any[] = [];
            
            positions.forEach((position) => {
                lineItems.push({
                    variant: {
                        id: position.product_id,
                    },
                    quantity: position.amount,
                    discountedTotalSet: {
                        presentmentMoney: {
                            amount: position.amount,
                        },
                    },
                });
            });

            if (lineItems.length > 0) {
                object.lineItems = lineItems
            }
        }

        return object;
    })
}

const getCustomerObject = (customer: TCustomer) => {

    const object: any = {
        id: customer.customer_id,
        defaultEmailAddress: {
            emailAddress: customer.email,
        },
        firstName: customer.first_name,
        lastName: customer.lastname,
        defaultPhoneNumber: {
            phoneNumber: customer.phone,
        },
        defaultAddress: {
            zip: customer.zip_code,
        },
    };

    const defaultDate = new Date("2000-01-01T00:00:00")

    if (customer?.nlf_time && customer.nlf_time !== defaultDate) {
        object.defaultEmailAddress.marketingUpdatedAt = customer.nlf_time.toDateString() 
    }

    if (customer?.registration && customer.registration !== defaultDate) {
        object.createdAt = customer.registration.toDateString() 
    }

    if (customer?.last_modification_date && customer.last_modification_date !== defaultDate) {
        object.updatedAt = customer.last_modification_date.toDateString() 
    }

    return object;
}

export const getCustomerData = async (payload: any) => {
    const orderIds = payload?.orders_requested || null;
    const customerEmail = payload?.customer?.email || null;

    let customer: TCustomer | null = null;
    let orders: TOrder[] = [];

    if (customerEmail) {
        customer = await getCustomer(customerEmail);
    }

    if (orderIds) {
        orders = await getOrders(orderIds);
    }

    if (!customer && orders.length === 0) {
        return null;
    }

    let result: TCustomerDataResult = {};

    if (customer) {
        result.customer = getCustomerObject(customer);
    }

    if (orders) {
        result.orders = getOrderObjects(orders);
    }

    return result;
}

export const deleteCustomer = async (email: string) => {
    return await prisma.customers.deleteMany({
        where: {
            email,
        },
    });
}

export const deleteOrders = async (ids: number[]) => {
    const orderIds = ids.map(id => id.toString());
    return await prisma.orders.deleteMany({
        where: {
            order_id: { in: orderIds },
        },
    });
}

export const deleteCustomerData = async (payload: any) => {
    const customerEmail = payload?.customer?.email || null;

    if (customerEmail) {
        return await deleteCustomer(customerEmail);
    }

    const orderIds: number[] | null = payload?.orders_to_redact || null;

    if (!orderIds) {
        return null;
    }

    return await deleteOrders(orderIds);
}

export const deleteShop = async (payload: any) => {
    const shopDomain = payload?.shop_domain || null;

    if (!shopDomain) {
        return null;
    }

    return await prisma.user.deleteMany({
        where: {
            username: shopDomain,
        },
    });
}
