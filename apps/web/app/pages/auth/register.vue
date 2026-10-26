<script lang="ts" setup>
definePageMeta({
  middleware: [
    () => {
      return navigateTo('/auth/login', { replace: true });
    },
  ],
});
</script>
<template>
  <div />
</template>
