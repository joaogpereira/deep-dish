// src/App.tsx
import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider } from "@/contexts/AuthContext";
import { ThemeProvider } from "next-themes";
import { Capacitor } from "@capacitor/core";

import PublicLayout from "@/components/layouts/PublicLayout";
import AppLayout from "@/components/layouts/AppLayout";
import AdminLayout from "@/components/layouts/AdminLayout";
import ProtectedRoute from "@/components/ProtectedRoute";
import GuestRoute from "@/components/GuestRoute";
import BotaoVoltarAndroid from "@/components/BotaoVoltarAndroid";
import BarrasDoSistemaNativo from "@/components/BarrasDoSistemaNativo";

import Landing from "@/pages/Landing";
import Login from "@/pages/Login";
import Register from "@/pages/Register";
import RestaurantLogin from "@/pages/RestaurantLogin";
import RestaurantRegister from "@/pages/RestaurantRegister";
import VerifyEmail from "@/pages/VerifyEmail";
import ForgotPassword from "@/pages/ForgotPassword";
import ResetPassword from "@/pages/ResetPassword";
import NotFound from "@/pages/NotFound";

import AppHome from "@/pages/app/Home";
import Search from "@/pages/app/Search";
import Restaurants from "@/pages/app/Restaurants";
import RestaurantDetail from "@/pages/app/RestaurantDetail";
import Confirm from "@/pages/app/Confirm";
import Queue from "@/pages/app/Queue";
import ReservationDetail from "@/pages/app/ReservationDetail";

import Dashboard from "@/pages/restaurant/Dashboard";
import Settings from "@/pages/restaurant/Settings";
import Tables from "@/pages/restaurant/Tables";
import QueueManagement from "@/pages/restaurant/QueueManagement";
import AdminReservations from "@/pages/restaurant/Reservations";
import Staff from "@/pages/restaurant/Staff";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false}>
      <AuthProvider>
        <TooltipProvider>
          <Toaster />
          <Sonner />
          <BarrasDoSistemaNativo />
        <BrowserRouter>
          <BotaoVoltarAndroid />
          <Routes>
            {/* Public */}
            <Route element={<PublicLayout />}>
              {/* O app nativo é só do cliente: abre no login, não na landing. */}
              <Route
                path="/"
                element={Capacitor.isNativePlatform() ? <Navigate to="/login" replace /> : <Landing />}
              />
              <Route path="/login" element={<GuestRoute><Login /></GuestRoute>} />
              <Route path="/register" element={<GuestRoute><Register /></GuestRoute>} />
              <Route path="/restaurant/login" element={<GuestRoute><RestaurantLogin /></GuestRoute>} />
              <Route path="/restaurant/register" element={<GuestRoute><RestaurantRegister /></GuestRoute>} />
              <Route path="/forgot-password" element={<GuestRoute><ForgotPassword tipo="cliente" /></GuestRoute>} />
              <Route path="/restaurant/forgot-password" element={<GuestRoute><ForgotPassword tipo="restaurante" /></GuestRoute>} />
              <Route path="/reset-password" element={<GuestRoute><ResetPassword /></GuestRoute>} />
              <Route path="/verify-email" element={<VerifyEmail />} />
            </Route>

            {/* Client App (só cliente — reserva/fila são ações do cliente, o
                backend rejeita token de restaurante nessas rotas de qualquer forma) */}
            <Route
              element={
                <ProtectedRoute allow={["cliente"]}>
                  <AppLayout />
                </ProtectedRoute>
              }
            >
              <Route path="/app" element={<AppHome />} />
              <Route path="/app/search" element={<Search />} />
              <Route path="/app/restaurants" element={<Restaurants />} />
              <Route path="/app/restaurants/:id" element={<RestaurantDetail />} />
              <Route path="/app/confirm" element={<Confirm />} />
              <Route path="/app/queue" element={<Queue />} />
              <Route
                path="/app/reservations/:id"
                element={<ReservationDetail />}
              />
            </Route>

            {/* Restaurant Admin (só restaurante) */}
            <Route
              element={
                <ProtectedRoute allow={["restaurante"]}>
                  <AdminLayout />
                </ProtectedRoute>
              }
            >
              <Route path="/restaurant/dashboard" element={<Dashboard />} />
              <Route path="/restaurant/settings" element={<Settings />} />
              <Route path="/restaurant/tables" element={<Tables />} />
              <Route path="/restaurant/queue" element={<QueueManagement />} />
              <Route
                path="/restaurant/reservations"
                element={<AdminReservations />}
              />
              <Route path="/restaurant/staff" element={<Staff />} />
            </Route>

            <Route path="*" element={<NotFound />} />
          </Routes>
        </BrowserRouter>
        </TooltipProvider>
      </AuthProvider>
    </ThemeProvider>
  </QueryClientProvider>
);

export default App;