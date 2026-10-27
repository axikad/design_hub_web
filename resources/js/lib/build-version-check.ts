type ViteManifest = Record<string, { file?: string }>;

const APP_ENTRY = 'resources/js/app.tsx';
const CHECK_INTERVAL_MS = 5 * 60 * 1000;

function findLoadedAppAsset(): { assetPath: string; buildBase: string } | null {
    const assetPath = Array.from(document.querySelectorAll<HTMLScriptElement>('script[type="module"][src]'))
        .map((script) => new URL(script.src, window.location.origin).pathname)
        .find((path) => /\/assets\/app-[^/]+\.js$/.test(path));

    if (!assetPath) {
        return null;
    }

    return {
        assetPath,
        buildBase: assetPath.slice(0, assetPath.lastIndexOf('/assets/')),
    };
}

export function startBuildVersionCheck(): void {
    if (!import.meta.env.PROD || typeof window === 'undefined') {
        return;
    }

    const loadedApp = findLoadedAppAsset();

    if (!loadedApp) {
        return;
    }

    let checking = false;

    const checkForNewBuild = async (): Promise<void> => {
        if (checking) {
            return;
        }

        checking = true;

        try {
            const manifestUrl = new URL(`${loadedApp.buildBase}/manifest.json`, window.location.origin);
            manifestUrl.searchParams.set('versionCheck', Date.now().toString());

            const response = await fetch(manifestUrl, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const manifest = (await response.json()) as ViteManifest;
            const latestFile = manifest[APP_ENTRY]?.file;

            if (!latestFile) {
                return;
            }

            const latestAssetPath = `${loadedApp.buildBase}/${latestFile.replace(/^\/+/, '')}`;

            if (latestAssetPath !== loadedApp.assetPath) {
                window.location.reload();
            }
        } catch {
            // A temporary network failure must not interrupt the current session.
        } finally {
            checking = false;
        }
    };

    const checkWhenVisible = (): void => {
        if (document.visibilityState === 'visible') {
            void checkForNewBuild();
        }
    };

    document.addEventListener('visibilitychange', checkWhenVisible);
    window.addEventListener('focus', checkWhenVisible);

    window.setInterval(checkWhenVisible, CHECK_INTERVAL_MS);
    void checkForNewBuild();
}
