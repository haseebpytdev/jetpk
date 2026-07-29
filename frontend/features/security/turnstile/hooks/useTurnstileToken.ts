"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchTurnstileConfig } from "../services/turnstile-config";
import type { TurnstilePublicConfig } from "../types";

type UseTurnstileTokenResult = {
  config: TurnstilePublicConfig | null;
  loading: boolean;
  token: string | null;
  tokenRequired: boolean;
  tokenExpired: boolean;
  tokenError: boolean;
  scriptFailed: boolean;
  resetSignal: number;
  setToken: (token: string | null) => void;
  markExpired: () => void;
  markError: () => void;
  markScriptFailed: () => void;
  resetToken: () => void;
};

export function useTurnstileToken(): UseTurnstileTokenResult {
  const [config, setConfig] = useState<TurnstilePublicConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);
  const [tokenExpired, setTokenExpired] = useState(false);
  const [tokenError, setTokenError] = useState(false);
  const [scriptFailed, setScriptFailed] = useState(false);
  const [resetSignal, setResetSignal] = useState(0);

  useEffect(() => {
    let cancelled = false;
    void fetchTurnstileConfig().then((nextConfig) => {
      if (cancelled) return;
      setConfig(nextConfig);
      setLoading(false);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  const tokenRequired = Boolean(config?.enabled && config.site_key);

  const resetToken = useCallback(() => {
    setToken(null);
    setTokenExpired(false);
    setTokenError(false);
    setResetSignal((value) => value + 1);
  }, []);

  const markExpired = useCallback(() => {
    setToken(null);
    setTokenExpired(true);
    setTokenError(false);
  }, []);

  const markError = useCallback(() => {
    setToken(null);
    setTokenError(true);
    setTokenExpired(false);
  }, []);

  const markScriptFailed = useCallback(() => {
    setScriptFailed(true);
    setToken(null);
  }, []);

  return {
    config,
    loading,
    token,
    tokenRequired,
    tokenExpired,
    tokenError,
    scriptFailed,
    resetSignal,
    setToken,
    markExpired,
    markError,
    markScriptFailed,
    resetToken,
  };
}
