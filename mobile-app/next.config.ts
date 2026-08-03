import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  webpack: (config) => {
    // onnxruntime-web (bundled inside @paddleocr/paddleocr-js) locates its
    // own bundle via `new URL("ort.bundle.min.mjs", import.meta.url)`. By
    // default webpack statically resolves that as an asset module relative to
    // the file and fails because the file only ships inside onnxruntime-web's
    // dist. `importMeta: false` keeps import.meta.url a runtime expression,
    // which is exactly what onnxruntime-web expects (see microsoft/onnxruntime
    // issue #22113). Scoped to just these packages so Next's own code still
    // gets normal import.meta handling.
    config.module.rules.push({
      test: /node_modules[\\/](@paddleocr[\\/]paddleocr-js|onnxruntime-web)[\\/].*\.(mjs|js)$/,
      parser: { javascript: { importMeta: false } },
    });

    // @techstark/opencv-js (also bundled by the SDK) is an Emscripten build
    // that statically `require("fs")`/`require("path")`/`require("crypto")`.
    // Those branches only execute when running in Node (ENVIRONMENT_HAS_NODE);
    // in the browser they are never reached, so an empty stub is safe and
    // stops webpack from failing to resolve Node core modules in the client
    // bundle.
    config.resolve.fallback = {
      ...config.resolve.fallback,
      fs: false,
      path: false,
      crypto: false,
    };

    return config;
  },
};

export default nextConfig;
