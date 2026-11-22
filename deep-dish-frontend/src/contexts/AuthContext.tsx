import React, { createContext, useContext, useState, useCallback, useEffect } from "react";
import { Cliente, Restaurante } from "@/types";
import { authService } from "@/services/auth.service";
import { disconnectEcho } from "@/lib/echo";

type User = Cliente | Restaurante;

type RestaurantRegisterData = {
  name: string;
  email: string;
  password: string;
  cnpj: string;
  tipo: string;
  logradouro: string;
  numero: string;
  complemento?: string;
  bairro: string;
  cidade: string;
  estado: string;
  cep: string;
};

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  loginRestaurant: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, cpf: string, password: string) => Promise<void>;
  registerRestaurant: (data: RestaurantRegisterData) => Promise<void>;
  updateRestaurant: (data: Partial<Restaurante>) => Promise<void>;           // ✅ novo
  uploadImagemRestaurant: (file: File) => Promise<void>;                     // ✅ novo
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // ─── Restaurar sessão ao carregar ────────────────────────
  useEffect(() => {
    const token = localStorage.getItem("jwt");
    const tipo  = localStorage.getItem("tipo_usuario");

    if (!token || !tipo) {
      setIsLoading(false);
      return;
    }

    const restore = async () => {
      try {
        const data = tipo === "restaurante"
          ? await authService.getMeRestaurante()
          : await authService.getMeCliente();

        setUser(data);
        localStorage.setItem("user", JSON.stringify(data));
      } catch (err) {
        const isEmailNotVerified = err instanceof Error && err.message === 'email_not_verified';
        if (!isEmailNotVerified) {
          localStorage.removeItem("jwt");
          localStorage.removeItem("tipo_usuario");
          localStorage.removeItem("user");
        }
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    };

    restore();
  }, []);

  // ─── LOGIN CLIENTE ───────────────────────────────────────
  const login = useCallback(async (email: string, password: string) => {
    const { token } = await authService.loginCliente(email, password);
    localStorage.setItem("jwt", token);
    localStorage.setItem("tipo_usuario", "cliente");
    try {
      const cliente = await authService.getMeCliente();
      setUser(cliente);
      localStorage.setItem("user", JSON.stringify(cliente));
    } catch (err) {
      if (err instanceof Error && err.message === 'email_not_verified') return;
      throw err;
    }
  }, []);

  // ─── LOGIN RESTAURANTE ───────────────────────────────────
  const loginRestaurant = useCallback(async (email: string, password: string) => {
    const { token } = await authService.loginRestaurante(email, password);
    localStorage.setItem("jwt", token);
    localStorage.setItem("tipo_usuario", "restaurante");
    try {
      const restaurante = await authService.getMeRestaurante();
      setUser(restaurante);
      localStorage.setItem("user", JSON.stringify(restaurante));
    } catch (err) {
      if (err instanceof Error && err.message === 'email_not_verified') return;
      throw err;
    }
  }, []);

  // ─── REGISTER CLIENTE ────────────────────────────────────
  const register = useCallback(async (
    name: string,
    email: string,
    cpf: string,
    password: string
  ) => {
    const result = await authService.registerCliente(name, email, cpf, password);
    localStorage.setItem("jwt", result.token);
    localStorage.setItem("tipo_usuario", "cliente");
    try {
      const cliente = result.cliente ?? await authService.getMeCliente();
      setUser(cliente);
      localStorage.setItem("user", JSON.stringify(cliente));
    } catch (err) {
      if (err instanceof Error && err.message === 'email_not_verified') return;
      throw err;
    }
  }, []);

  // ─── REGISTER RESTAURANTE ────────────────────────────────
  const registerRestaurant = useCallback(async (data: RestaurantRegisterData) => {
    const result = await authService.registerRestaurante(data);
    localStorage.setItem("jwt", result.token);
    localStorage.setItem("tipo_usuario", "restaurante");
    try {
      const restaurante = result.restaurante ?? await authService.getMeRestaurante();
      setUser(restaurante);
      localStorage.setItem("user", JSON.stringify(restaurante));
    } catch (err) {
      if (err instanceof Error && err.message === 'email_not_verified') return;
      throw err;
    }
  }, []);

  // ─── UPDATE RESTAURANTE ──────────────────────────────────
  const updateRestaurant = useCallback(async (data: Partial<Restaurante>) => {
    setIsLoading(true);
    try {
      // envia os dados atualizados para o backend
      const restaurante = await authService.updateRestaurante(data);

      // atualiza o estado global e o localStorage
      setUser(restaurante);
      localStorage.setItem("user", JSON.stringify(restaurante));
    } finally {
      setIsLoading(false);
    }
  }, []);

  // ─── UPLOAD IMAGEM RESTAURANTE ───────────────────────────
  const uploadImagemRestaurant = useCallback(async (file: File) => {
    setIsLoading(true);
    try {
      // faz upload e recebe a URL da imagem
      const { imagem_url } = await authService.uploadImagemRestaurante(file);

      // atualiza só o campo imagem_url no estado sem precisar chamar /me novamente
      setUser(prev => {
        if (!prev) return prev;
        const updated = { ...prev, imagem_url };
        localStorage.setItem("user", JSON.stringify(updated));
        return updated;
      });
    } finally {
      setIsLoading(false);
    }
  }, []);

  // ─── LOGOUT ──────────────────────────────────────────────
  const logout = useCallback(async () => {
    const tipo = localStorage.getItem("tipo_usuario");
    try {
      if (tipo === "restaurante") {
        await authService.logoutRestaurante();
      } else {
        await authService.logoutCliente();
      }
    } catch {
      // rede falhou — mesmo assim desloga localmente
    } finally {
      setUser(null);
      disconnectEcho(); // sem isto o socket segue aberto depois do logout
      localStorage.removeItem("jwt");
      localStorage.removeItem("tipo_usuario");
      localStorage.removeItem("user");
    }
  }, []);

  return (
    <AuthContext.Provider value={{
      user,
      isAuthenticated: !!user,
      isLoading,
      login,
      loginRestaurant,
      register,
      registerRestaurant,
      updateRestaurant,        // ✅ exposto
      uploadImagemRestaurant,  // ✅ exposto
      logout,
    }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
};