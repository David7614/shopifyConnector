import prisma from "../db.server";

export const setIntegrationData = async (userId: number, key: string, value: any) => {
  const config = await prisma.integration_data.findFirst({
    where: {
      customer_id: userId,
      task: key,
    },
  });

  if (config) {
    return await prisma.integration_data.update({
      where: {
        id: config.id,
      },
      data: {
        value,
      },
    });
  }

  return await prisma.integration_data.create({
    data: {
      task: key,
      value,
      customer_id: userId,
    },
  });
};

export const getIntegrationData = async (userId: number, key: string) => {
  const config = await prisma.integration_data.findFirst({
    where: {
      customer_id: userId,
      task: key,
    },
  });

  if (!config) {
    return null;
  }

  return config;
};
