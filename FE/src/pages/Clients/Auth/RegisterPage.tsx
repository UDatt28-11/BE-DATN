import { Form, Input, Button, Divider, Typography } from "antd";
import { GoogleOutlined } from "@ant-design/icons";
// Import 'useNavigate' để chuyển hướng
import { Link, useNavigate } from "react-router-dom";
import React from "react";

const { Title, Text } = Typography;

const RegisterPage: React.FC = () => {
    const navigate = useNavigate();

    const onFinish = (values: any) => {
        console.log("Form values:", values);

        navigate("/login");


    };

    const overlayStyle: React.CSSProperties = {
        position: "fixed",
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: "rgba(0, 0, 0, 0.3)",
    };

    const formContainerStyle: React.CSSProperties = {
        backgroundColor: "white",
        width: "100%",
        maxWidth: "360px",
        borderRadius: "16px",
        boxShadow: "0 25px 50px -12px rgba(0, 0, 0, 0.25)",
        padding: "24px",
        zIndex: 10,
    };


    return (
        <div style={overlayStyle}>
            <div style={formContainerStyle}>
                <Title level={4} style={{ textAlign: "center", color: "#1890ff", marginBottom: "8px" }}>
                    Đăng ký tài khoản
                </Title>
                <p style={{ color: "#8c8c8c", textAlign: "center", fontSize: "14px", marginBottom: "24px" }}>
                    Hãy tạo tài khoản để bắt đầu
                </p>

                <Form layout="vertical" onFinish={onFinish}>
                    { }
                    <Form.Item name="name" rules={[{ required: true, message: "Vui lòng nhập họ tên" }]}>
                        <Input size="large" placeholder="Họ và tên" />
                    </Form.Item>

                    <Form.Item name="email" rules={[{ required: true, message: "Vui lòng nhập email" }, { type: "email", message: "Email không hợp lệ" }]}>
                        <Input size="large" placeholder="Email" />
                    </Form.Item>

                    <Form.Item name="password" rules={[{ required: true, message: "Vui lòng nhập mật khẩu" }]}>
                        <Input.Password size="large" placeholder="Mật khẩu" />
                    </Form.Item>

                    <Form.Item
                        name="confirmPassword"
                        dependencies={["password"]}
                        rules={[
                            { required: true, message: "Vui lòng nhập lại mật khẩu" },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (!value || getFieldValue("password") === value) {
                                        return Promise.resolve();
                                    }
                                    return Promise.reject(
                                        new Error("Mật khẩu nhập lại không khớp!")
                                    );
                                },
                            }),
                        ]}
                    >
                        <Input.Password size="large" placeholder="Nhập lại mật khẩu" />
                    </Form.Item>

                    <Button type="primary" htmlType="submit" block size="large">
                        Đăng ký
                    </Button>
                </Form>

                <Divider plain>Hoặc</Divider>

                <Button icon={<GoogleOutlined />} block size="large">
                    Đăng ký bằng Google
                </Button>

                <div style={{ textAlign: "center", marginTop: "16px", fontSize: "14px" }}>
                    <Text>Bạn đã có tài khoản? </Text>
                    <Link to="/login" style={{ color: "#1890ff" }}>
                        Đăng nhập
                    </Link>
                </div>
            </div>
        </div>
    );
};

export default RegisterPage;