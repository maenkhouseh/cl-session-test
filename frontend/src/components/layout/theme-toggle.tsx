"use client";

import { Moon, Sun } from "lucide-react";
import * as React from "react";
import { Button } from "@/components/ui/button";

export function ThemeToggle() {
  const toggle = React.useCallback(() => {
    const dark = document.documentElement.classList.toggle("dark");
    try {
      localStorage.setItem("theme", dark ? "dark" : "light");
    } catch {
      /* storage may be unavailable (private mode) — the preference just won't persist */
    }
  }, []);

  return (
    <Button variant="ghost" size="icon" onClick={toggle} aria-label="Toggle theme">
      <Sun className="hidden dark:block" />
      <Moon className="dark:hidden" />
    </Button>
  );
}
