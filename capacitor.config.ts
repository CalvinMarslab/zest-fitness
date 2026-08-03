import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.zestathletic.app',
    appName: 'Zest Athletic',
    webDir: 'public/build',
    server: {
        url: 'https://zestathletic.com',
        cleartext: false,
    },
    ios: {
        contentInset: 'always',
    },
};

export default config;
