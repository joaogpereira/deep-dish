import React, { useEffect, useRef } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';

// Voltar numa tela-raiz minimiza o app, como nos apps nativos — navegar dali
// levaria de volta ao login, que só redirecionaria para /app de novo.
const RAIZES = ['/app', '/login'];

/**
 * Gesto/botão de voltar do Android. Sem este listener o Capacitor fecha o app
 * em vez de navegar. Fora do app nativo não faz nada.
 */
const BotaoVoltarAndroid: React.FC = () => {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const pathnameRef = useRef(pathname);
  pathnameRef.current = pathname;

  useEffect(() => {
    if (!Capacitor.isNativePlatform()) return;

    const listener = App.addListener('backButton', ({ canGoBack }) => {
      // Modal aberto (Radix): voltar fecha o modal, não a tela por trás dele.
      if (document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]')) {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        return;
      }
      if (!canGoBack || RAIZES.includes(pathnameRef.current)) {
        App.minimizeApp();
        return;
      }
      navigate(-1);
    });

    return () => { listener.then(l => l.remove()); };
  }, [navigate]);

  return null;
};

export default BotaoVoltarAndroid;
