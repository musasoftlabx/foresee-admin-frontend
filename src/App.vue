<template>
  <v-app id="app">
    <vue-progress-bar />
    <component v-bind:is="component" />
    <vue-page-transition name="flip-y">
      <router-view />
    </vue-page-transition>
  </v-app>
</template>

<script>
import Appbar from "@/components/Appbar";
export default {
  components: {
    Appbar,
  },
  data() {
    return {
      component: "",
    };
  },
  beforeCreate() {
    if (this.$route.path !== "/Login") {
      this.component = "Appbar";
      localStorage.lastRoute = this.$route.path;
    }
    localStorage._4c_token_ &&
      this.$store.dispatch("StoreToken", localStorage._4c_token_);
  },
};
</script>

<style>
@import url("https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap");

.fancy-theme-material,
.fancy-panel .fancy-grid-cell,
.fancy-grid-header-cell-text {
  font-family: "Rubik", sans-serif !important;
}
#app {
  font-family: "Rubik", sans-serif !important;
}
#app.v-application [class*="text-"] {
  font-family: "Rubik", sans-serif !important;
}
.GradientButton {
  background-image: linear-gradient(
    -60deg,
    #ff5858 0%,
    #f09819 100%
  ) !important;
}
</style>
