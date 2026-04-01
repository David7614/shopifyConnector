import prisma from "../db.server";

export const setConfig = async (userId: number, key: string, value: any) => {
  const config = await prisma.user_config.findFirst({
    where: {
      id_user: userId,
      key,
    },
  });

  if (config) {
    return await prisma.user_config.update({
      where: {
        id: config.id,
      },
      data: {
        value,
      },
    });
  }

  return await prisma.user_config.create({
    data: {
      key,
      value,
      id_user: userId,
    },
  });
};

export const getConfig = async (userId: number, key: string) => {
  const config = await prisma.user_config.findFirst({
    where: {
      id_user: userId,
      key,
    },
  });

  if (!config) {
    return null;
  }

  return config;
};
