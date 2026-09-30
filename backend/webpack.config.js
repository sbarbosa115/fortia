import Encore from '@symfony/webpack-encore';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

// Two React apps in one Symfony project: the operator console (/console) and the respondent app (public routes).
// Both share assets/shared; each is Feature-Sliced (app, pages, widgets, features, entities).
Encore.setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('console', './assets/console/app/index.tsx')
  .addEntry('respondent', './assets/respondent/app/index.tsx')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .enableReactPreset()
  // Babel strips the types; `npm run typecheck` (tsc) checks them.
  .enableBabelTypeScriptPreset()
  .addAliases({
    '@shared': path.resolve(root, 'assets/shared'),
    '@console': path.resolve(root, 'assets/console'),
    '@respondent': path.resolve(root, 'assets/respondent'),
    '@api-types': path.resolve(root, 'assets/types'),
  });

const config = await Encore.getWebpackConfig();
// Polling on Windows/Docker bind mounts, where file events do not reach the container.
config.watchOptions = {poll: 1000, ignored: /node_modules|public\/build/};

export default config;
