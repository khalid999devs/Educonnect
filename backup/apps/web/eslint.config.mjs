import { educonnectBase } from "@educonnect/config/eslint/base";
import nextPlugin from "@next/eslint-plugin-next";

export default [...educonnectBase, nextPlugin.configs["core-web-vitals"]];
