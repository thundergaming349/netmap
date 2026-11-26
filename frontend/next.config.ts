import net from "node:net";
import type { NextConfig } from "next";

// Development only: proxy the Laravel routes so the browser sees one origin, like Caddy does in production.
const backendPaths = [
  "/api/:path*",
  "/auth/:path*",
  "/sanctum/:path*",
  "/broadcasting/:path*",
  "/webhook/:path*",
];

function canConnect(url: string): Promise<boolean> {
  const { hostname, port } = new URL(url);
  const targetPort = Number(port) || 80;

  return new Promise((resolve) => {
    const socket = net.connect({ host: hostname, port: targetPort, timeout: 200 });
    socket.once("connect", () => {
      socket.destroy();
      resolve(true);
    });
    socket.once("error", () => resolve(false));
    socket.once("timeout", () => {
      socket.destroy();
      resolve(false);
    });
  });
}

async function resolveBackendUrl(): Promise<string | undefined> {
  const configured =
    process.env.NETMAP_BACKEND_URL ??
    (process.env.NODE_ENV === "development" ? "http://127.0.0.1:8010" : undefined);

  if (!configured || process.env.NODE_ENV !== "development") {
    return configured;
  }

  if (await canConnect(configured)) {
    return configured;
  }

  const fallback = "http://127.0.0.1:8000";
  if (configured !== fallback && (await canConnect(fallback))) {
    return fallback;
  }

  return configured;
}

const nextConfig: NextConfig = {
  // Self-contained server for the Docker image (infra/frontend.Dockerfile).
  output: "standalone",
  cacheComponents: true,
  partialPrefetching: true,
  experimental: {
    proxyTimeout: 300_000,
    instantInsights: {
      validationLevel: "manual-warning",
    },
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async rewrites() {
    const backendUrl = await resolveBackendUrl();
    if (!backendUrl) {
      return [];
    }

    const routes = backendPaths.map((source) => ({
      source,
      destination: `${backendUrl}${source}`,
    }));

    routes.push({
      source: "/stream/:path*",
      destination: "http://127.0.0.1:1984/:path*",
    });

    return routes;
  },
};

export default nextConfig;
