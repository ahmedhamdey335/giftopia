import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  experimental: {
    typedRoutes: false,
  },
  reactStrictMode: true,
};

export default nextConfig;
