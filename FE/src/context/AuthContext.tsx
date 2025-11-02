import React, { createContext, useContext, useState, useMemo, ReactNode } from 'react';

// 1. Định nghĩa kiểu dữ liệu cho Context
interface AuthContextType {
    isLoggedIn: boolean;
    login: () => void;
    logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// 2. Định nghĩa Props cho Provider
interface AuthProviderProps {
    children: ReactNode;
}

// 3. Tạo Provider Component
export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
    const [isLoggedIn, setIsLoggedIn] = useState(false);

    // Logic mô phỏng đăng nhập
    const login = () => {
        console.log("LOGIN: Thiết lập trạng thái đăng nhập = TRUE.");
        setIsLoggedIn(true);
    };

    // Logic mô phỏng đăng xuất
    const logout = () => {
        console.log("LOGOUT: Thiết lập trạng thái đăng nhập = FALSE.");
        setIsLoggedIn(false);
    };

    const value = useMemo(() => ({
        isLoggedIn,
        login,
        logout,
    }), [isLoggedIn]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

// 4. Custom Hook để sử dụng Context
export const useAuth = () => {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
