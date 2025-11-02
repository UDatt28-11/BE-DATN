import React from "react";
import { Outlet } from "react-router-dom";
import AppHeader from "./AppHeader";

const AppLayout: React.FC = () => {
    return (
        <>
            <AppHeader />
            <Outlet />
        </>
    );
};

export default AppLayout;
