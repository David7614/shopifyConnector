import type { Session } from "@shopify/shopify-app-remix/server";
import type { xml_feed_queue as TFeedQueue } from '@prisma/client';
import { createHash, randomBytes } from "crypto";
import { setConfig } from "app/models/user-config.server";
import prisma from "../db.server";
import bcrypt from "bcrypt";

export type IUser = {
  username: string;
  email: string;
  shopType: string;
  password: string;
  clientId: string;
  clientSecret: string;
  registerDate: any;
  active: number;
  registerToken: string;
  uuid: string;
};

const mapUser = (user: IUser) => ({
  username: user.username,
  email: user.email,
  shop_type: user.shopType,
  password: user.password,
  client_id: user.clientId,
  client_secret: user.clientSecret,
  register_date: user.registerDate,
  active: user.active,
  registerToken: user.registerToken,
  uuid: user.uuid,
});

export const getUsers = async () => {
  return await prisma.user.findMany();
};

export const getUser = async (username: string) => {
  return await prisma.user.findFirst({
    where: {
      username,
    },
  });
};

export const updateUser = async (id: any, user: IUser) => {
  return await prisma.user.update({
    where: {
      id,
    },
    data: mapUser(user),
  });
};

export const createUser = async (user: IUser) => {
  return await prisma.user.create({
    data: mapUser(user),
  });
};

enum EQueueType {
  PRODUCT = "product",
  CUSTOMER = "customer",
  ORDER = "order",
}

enum EQueueStep {
  FETCH = 0,
  GENERATE = 1,
}

function dateWithMinutes(date: Date, minutes: number) {
  const dateCopy = new Date(date);
  dateCopy.setMinutes(date.getMinutes() + minutes);  
  return dateCopy;
}

const createSingleQueue = async (userId: number, type: EQueueType, step: EQueueStep) => {
  try {
    const queue: Omit<TFeedQueue, "id"> = {
      integrated: 0,
      next_integration_date: step === EQueueStep.FETCH ? new Date() : dateWithMinutes(new Date(), 1),
      executed_at: new Date("2000-01-01"),
      finished_at: new Date("2000-01-01"),
      integration_type: type,
      current_integrate_user: userId,
      page: 0,
      max_page: 0,
      parameters: step === EQueueStep.FETCH ?  'a:0:{}' : 'a:1:{s:12:"objects_done";i:1;}',
    }

    await prisma.xml_feed_queue.create({
      data: queue,
    });

    return true
  } catch (e) {
    console.log(">----------- create single queue erorr:")
    console.log(e)
    console.log("<-----------")
  }

  return false
}

export const createInitialQueue = async (userId: number) => {
  setConfig(userId, 'initial_queue_done', '1');

  await createSingleQueue(userId, EQueueType.CUSTOMER, EQueueStep.FETCH)
  await createSingleQueue(userId, EQueueType.CUSTOMER, EQueueStep.GENERATE)

  await createSingleQueue(userId, EQueueType.PRODUCT, EQueueStep.FETCH)
  await createSingleQueue(userId, EQueueType.PRODUCT, EQueueStep.GENERATE)

  await createSingleQueue(userId, EQueueType.ORDER, EQueueStep.FETCH)
  await createSingleQueue(userId, EQueueType.ORDER, EQueueStep.GENERATE)
};

function getRegisterToken() {
  const rand = randomBytes(16).toString("hex");
  const time = Math.floor(Date.now() / 1000);
  return createHash("md5").update(`text${rand}${time}`).digest("hex");
}

async function hashPassword(password: string): Promise<string> {
  const saltRounds = 10;
  return await bcrypt.hash(password, saltRounds);
}

function generateSha1(input: string): string {
  const hash = createHash("sha1");
  hash.update(input);
  return hash.digest("hex");
}

function generateMD5(input: string): string {
  return createHash("md5").update(input).digest("hex");
}

function generateSha256(input: string): string {
  return createHash("sha256").update(input).digest("hex");
}

function getCurrentDate() {
  return new Date().toISOString();
}

export const handleUserAccess = async (session: Session) => {
  const username = session.shop;

  const user = await getUser(username);

  if (!user) {
    const email = `${session.id}@shopify.com`;
    const clientId = generateSha1(username + email);

    const user: IUser = {
      username,
      email,
      shopType: "shopify",
      password: await hashPassword("cokolwiek_samba_shopify"),
      clientId,
      clientSecret: generateMD5(generateSha256(clientId + username)),
      registerDate: getCurrentDate(),
      active: 1,
      registerToken: getRegisterToken(),
      uuid: generateMD5(randomBytes(16).toString("hex")),
    };

    await createUser(user);
  }
};

export const getFeedOverview = async (uuid: string) => {
  const backendUrl = process.env.BACKEND_URL

  const res = await fetch(`${backendUrl}/feed?id=${uuid}`, {
    method: "GET",
  });

  if (!res.ok) {
    return null
  }

  return await res.json()
}

