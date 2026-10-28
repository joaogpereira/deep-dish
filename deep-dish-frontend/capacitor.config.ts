import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.deepdish.app',
  appName: 'Deep Dish',
  webDir: 'dist',
  android: {
    // O app roda em https://localhost; em desenvolvimento a API é http no IP da
    // máquina (ver .env.mobile.example). Sem isto a WebView bloqueia como mixed content.
    allowMixedContent: true,
  },
  plugins: {
    SystemBars: {
      // index.html usa viewport-fit=cover; avisar já no boot evita o salto de layout.
      initialViewportFitValueHint: 'cover',
    },
  },
};

export default config;
