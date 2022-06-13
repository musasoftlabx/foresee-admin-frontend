module.exports = {
  productionSourceMap: false,
  //publicPath: process.env.NODE_ENV === "production" ? "" : "/",
  pwa: {
    name: "4C IMS",
    themeColor: "#39B54A",
    msTileColor: "#39B54A",
    manifestOptions: {
      background_color: "#39B54A",
    },
  },
};
