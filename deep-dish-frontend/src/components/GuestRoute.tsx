import React from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import { User } from "@/types";
import { lerDestino } from "@/lib/redirectAposLogin";

function isRestaurante(user: User): boolean {
  return !!user && typeof user === "object" && "cnpj" in user;
}

const GuestRoute: React.FC<{ children: React.ReactElement }> = ({ children }) => {
  const { user, isAuthenticated, isLoading } = useAuth();

  if (isLoading) return null;

  if (isAuthenticated && user) {
    return (
      <Navigate
        to={isRestaurante(user) ? "/restaurant/dashboard" : (lerDestino() ?? "/app")}
        replace
      />
    );
  }

  return children;
};

export default GuestRoute;
