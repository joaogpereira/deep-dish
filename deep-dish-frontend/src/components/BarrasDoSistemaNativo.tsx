import React, { useEffect } from 'react';
import { useTheme } from 'next-themes';
import { Capacitor, SystemBars, SystemBarsStyle } from '@capacitor/core';

/**
 * Ícones da barra de status/gestos seguem o tema do app, não o do celular: o app
 * tem o próprio toggle, e com o celular em modo escuro os ícones ficariam brancos
 * sobre o cabeçalho bege. Fora do app nativo não faz nada.
 */
const BarrasDoSistemaNativo: React.FC = () => {
  const { resolvedTheme } = useTheme();

  useEffect(() => {
    if (!Capacitor.isNativePlatform()) return;
    SystemBars.setStyle({
      // Dark = ícones claros (para fundo escuro); Light = ícones escuros.
      style: resolvedTheme === 'dark' ? SystemBarsStyle.Dark : SystemBarsStyle.Light,
    });
  }, [resolvedTheme]);

  return null;
};

export default BarrasDoSistemaNativo;
