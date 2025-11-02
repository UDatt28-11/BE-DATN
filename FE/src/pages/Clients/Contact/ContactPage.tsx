import React, { useState } from "react";
import {
    Layout,
    Row,
    Col,
    Typography,
    Card,
    Form,
    Input,
    Button,
    Select,
    message,
    Breadcrumb,
    Space,
} from "antd";
import {
    HomeOutlined,
    MailOutlined,
    PhoneOutlined,
    EnvironmentOutlined,
    SendOutlined,
    ClockCircleOutlined,
    FacebookOutlined,
    MessageOutlined,
} from "@ant-design/icons";
import { Link } from "react-router-dom";

import AppHeader from "../../../components/Layout/AppHeader";
import AppFooter from "../../../components/Layout/AppFooter";

const { Content } = Layout;
const { Title, Text, Paragraph } = Typography;
const { TextArea } = Input;
const { Option } = Select;

const ContactPage: React.FC = () => {
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);

    // Xử lý gửi form
    const handleSubmit = async (values: any) => {
        setLoading(true);

        // Giả lập gửi form
        setTimeout(() => {
            console.log("Form values:", values);
            message.success("Gửi liên hệ thành công! Chúng tôi sẽ phản hồi trong 24h.");
            form.resetFields();
            setLoading(false);
        }, 1500);
    };

    return (
        <Layout style={{ minHeight: '100vh', background: '#f0f2f5' }}>
            <AppHeader />

            <Content style={{ marginTop: 64 }}>
                {/* Banner */}
                <div style={{
                    backdropFilter: 'blur(8px)',
                    background: 'linear-gradient(135deg, rgba(13,146,244,0.85) 0%, rgba(7,112,228,0.85) 100%)',
                    padding: '40px 24px',
                }}>

                    <div style={{ maxWidth: 1200, margin: '0 auto', textAlign: 'center' }}>
                        <MessageOutlined style={{ fontSize: 48, color: '#fff', marginBottom: 16 }} />
                        <Title level={1} style={{ color: '#fff', marginBottom: 8 }}>
                            Liên Hệ Với Chúng Tôi
                        </Title>
                        <Paragraph style={{ color: 'rgba(255,255,255,0.9)', fontSize: 16, marginBottom: 0 }}>
                            Chúng tôi luôn sẵn sàng hỗ trợ bạn 24/7
                        </Paragraph>
                    </div>
                </div>

                <div style={{ maxWidth: 1200, margin: '0 auto', padding: '24px 16px' }}>
                    {/* Breadcrumb */}
                    <Breadcrumb style={{ marginBottom: 24 }}>
                        <Breadcrumb.Item>
                            <Link to="/">
                                <HomeOutlined /> Trang chủ
                            </Link>
                        </Breadcrumb.Item>
                        <Breadcrumb.Item>Liên hệ</Breadcrumb.Item>
                    </Breadcrumb>

                    <Row gutter={[24, 24]}>
                        {/* Thông tin liên hệ */}
                        <Col xs={24} lg={10}>
                            <Space direction="vertical" size="large" style={{ width: '100%' }}>
                                {/* Thông tin chính */}
                                <Card>
                                    <Title level={4} style={{ marginBottom: 20 }}>
                                        Thông Tin Liên Hệ
                                    </Title>

                                    <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                                        {/* Địa chỉ */}
                                        <div style={{ display: 'flex', alignItems: 'flex-start' }}>
                                            <div style={{
                                                width: 40,
                                                height: 40,
                                                borderRadius: '50%',
                                                background: '#e6f7ff',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                marginRight: 12,
                                                flexShrink: 0,
                                            }}>
                                                <EnvironmentOutlined style={{ fontSize: 20, color: '#1890ff' }} />
                                            </div>
                                            <div>
                                                <Text strong style={{ display: 'block', marginBottom: 4 }}>
                                                    Địa chỉ
                                                </Text>
                                                <Text type="secondary">
                                                    Huyện Sóc Sơn, Hà Nội, Việt Nam
                                                </Text>
                                            </div>
                                        </div>

                                        {/* Số điện thoại */}
                                        <div style={{ display: 'flex', alignItems: 'flex-start' }}>
                                            <div style={{
                                                width: 40,
                                                height: 40,
                                                borderRadius: '50%',
                                                background: '#e6f7ff',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                marginRight: 12,
                                                flexShrink: 0,
                                            }}>
                                                <PhoneOutlined style={{ fontSize: 20, color: '#1890ff' }} />
                                            </div>
                                            <div>
                                                <Text strong style={{ display: 'block', marginBottom: 4 }}>
                                                    Số điện thoại
                                                </Text>
                                                <a href="tel:0123456789" style={{ color: '#1890ff' }}>
                                                    0123 456 789
                                                </a>
                                                <br />
                                                <a href="tel:0987654321" style={{ color: '#1890ff' }}>
                                                    0987 654 321
                                                </a>
                                            </div>
                                        </div>

                                        {/* Email */}
                                        <div style={{ display: 'flex', alignItems: 'flex-start' }}>
                                            <div style={{
                                                width: 40,
                                                height: 40,
                                                borderRadius: '50%',
                                                background: '#e6f7ff',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                marginRight: 12,
                                                flexShrink: 0,
                                            }}>
                                                <MailOutlined style={{ fontSize: 20, color: '#1890ff' }} />
                                            </div>
                                            <div>
                                                <Text strong style={{ display: 'block', marginBottom: 4 }}>
                                                    Email
                                                </Text>
                                                <a href="mailto:contact@socsonhomestay.vn" style={{ color: '#1890ff' }}>
                                                    contact@socsonhomestay.vn
                                                </a>
                                                <br />
                                                <a href="mailto:support@socsonhomestay.vn" style={{ color: '#1890ff' }}>
                                                    support@socsonhomestay.vn
                                                </a>
                                            </div>
                                        </div>

                                        {/* Giờ làm việc */}
                                        <div style={{ display: 'flex', alignItems: 'flex-start' }}>
                                            <div style={{
                                                width: 40,
                                                height: 40,
                                                borderRadius: '50%',
                                                background: '#e6f7ff',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                marginRight: 12,
                                                flexShrink: 0,
                                            }}>
                                                <ClockCircleOutlined style={{ fontSize: 20, color: '#1890ff' }} />
                                            </div>
                                            <div>
                                                <Text strong style={{ display: 'block', marginBottom: 4 }}>
                                                    Giờ làm việc
                                                </Text>
                                                <Text type="secondary">
                                                    Thứ 2 - Chủ Nhật: 8:00 - 22:00
                                                </Text>
                                            </div>
                                        </div>
                                    </Space>
                                </Card>

                                {/* Mạng xã hội */}
                                <Card>
                                    <Title level={4} style={{ marginBottom: 20 }}>
                                        Kết Nối Với Chúng Tôi
                                    </Title>
                                    <Space size="middle">
                                        <Button
                                            type="primary"
                                            shape="circle"
                                            size="large"
                                            icon={<FacebookOutlined />}
                                            href="https://facebook.com"
                                            target="_blank"
                                        />
                                        <Button
                                            type="primary"
                                            shape="circle"
                                            size="large"
                                            style={{ background: '#0088cc' }}
                                            icon={<MessageOutlined />}
                                            href="https://t.me"
                                            target="_blank"
                                        />
                                        <Button
                                            type="primary"
                                            shape="circle"
                                            size="large"
                                            style={{ background: '#25D366' }}
                                            icon={<PhoneOutlined />}
                                            href="https://wa.me/84123456789"
                                            target="_blank"
                                        />
                                    </Space>
                                </Card>

                                {/* Google Map */}
                                <Card
                                    title="Vị Trí Trên Bản Đồ"
                                    bodyStyle={{ padding: 0 }}
                                >
                                    <iframe
                                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d59587.94906794051!2d105.81917234863281!3d21.25!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135056c02855555%3A0x400a72a54ab0760!2zU-G7kWMgU8ahbiwgSMOgIE7hu5lpLCBWaeG7h3QgTmFt!5e0!3m2!1svi!2s!4v1234567890123!5m2!1svi!2s"
                                        width="100%"
                                        height="300"
                                        style={{ border: 0 }}
                                        allowFullScreen
                                        loading="lazy"
                                        referrerPolicy="no-referrer-when-downgrade"
                                    />
                                </Card>
                            </Space>
                        </Col>

                        {/* Form liên hệ */}
                        <Col xs={24} lg={14}>
                            <Card>
                                <Title level={4} style={{ marginBottom: 8 }}>
                                    Gửi Tin Nhắn Cho Chúng Tôi
                                </Title>
                                <Paragraph type="secondary" style={{ marginBottom: 24 }}>
                                    Vui lòng điền thông tin bên dưới, chúng tôi sẽ phản hồi trong vòng 24 giờ
                                </Paragraph>

                                <Form
                                    form={form}
                                    layout="vertical"
                                    onFinish={handleSubmit}
                                    requiredMark={false}
                                >
                                    <Row gutter={16}>
                                        {/* Họ tên */}
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Họ và tên"
                                                name="name"
                                                rules={[
                                                    { required: true, message: 'Vui lòng nhập họ tên' },
                                                    { min: 2, message: 'Họ tên phải có ít nhất 2 ký tự' }
                                                ]}
                                            >
                                                <Input
                                                    size="large"
                                                    placeholder="Nguyễn Văn A"
                                                />
                                            </Form.Item>
                                        </Col>

                                        {/* Số điện thoại */}
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Số điện thoại"
                                                name="phone"
                                                rules={[
                                                    { required: true, message: 'Vui lòng nhập số điện thoại' },
                                                    {
                                                        pattern: /^[0-9]{10,11}$/,
                                                        message: 'Số điện thoại không hợp lệ'
                                                    }
                                                ]}
                                            >
                                                <Input
                                                    size="large"
                                                    placeholder="0123456789"
                                                    maxLength={11}
                                                />
                                            </Form.Item>
                                        </Col>

                                        {/* Email */}
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Email"
                                                name="email"
                                                rules={[
                                                    { required: true, message: 'Vui lòng nhập email' },
                                                    { type: 'email', message: 'Email không hợp lệ' }
                                                ]}
                                            >
                                                <Input
                                                    size="large"
                                                    placeholder="example@email.com"
                                                />
                                            </Form.Item>
                                        </Col>

                                        {/* Lý do liên hệ */}
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Lý do liên hệ"
                                                name="reason"
                                                rules={[
                                                    { required: true, message: 'Vui lòng chọn lý do liên hệ' }
                                                ]}
                                            >
                                                <Select
                                                    size="large"
                                                    placeholder="Chọn lý do"
                                                >
                                                    <Option value="booking">Đặt phòng</Option>
                                                    <Option value="inquiry">Tư vấn</Option>
                                                    <Option value="complaint">Khiếu nại</Option>
                                                    <Option value="feedback">Góp ý</Option>
                                                    <Option value="partnership">Hợp tác</Option>
                                                    <Option value="other">Khác</Option>
                                                </Select>
                                            </Form.Item>
                                        </Col>

                                        {/* Nội dung tin nhắn */}
                                        <Col xs={24}>
                                            <Form.Item
                                                label="Nội dung tin nhắn"
                                                name="message"
                                                rules={[
                                                    { required: true, message: 'Vui lòng nhập nội dung' },
                                                    { min: 10, message: 'Nội dung phải có ít nhất 10 ký tự' }
                                                ]}
                                            >
                                                <TextArea
                                                    rows={6}
                                                    placeholder="Nhập nội dung bạn muốn gửi..."
                                                    showCount
                                                    maxLength={500}
                                                />
                                            </Form.Item>
                                        </Col>

                                        {/* Button gửi */}
                                        <Col xs={24}>
                                            <Form.Item style={{ marginBottom: 0 }}>
                                                <Button
                                                    type="primary"
                                                    size="large"
                                                    icon={<SendOutlined />}
                                                    htmlType="submit"
                                                    loading={loading}
                                                    block
                                                >
                                                    Gửi tin nhắn
                                                </Button>
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                </Form>
                            </Card>

                            {/* FAQs nhanh */}
                            <Card style={{ marginTop: 24 }}>
                                <Title level={5} style={{ marginBottom: 16 }}>
                                    Câu Hỏi Thường Gặp
                                </Title>
                                <Space direction="vertical" style={{ width: '100%' }} size="middle">
                                    <div>
                                        <Text strong>Làm sao để đặt phòng?</Text>
                                        <Paragraph type="secondary" style={{ marginBottom: 0, marginTop: 4 }}>
                                            Bạn có thể đặt phòng trực tiếp trên website hoặc liên hệ hotline để được hỗ trợ.
                                        </Paragraph>
                                    </div>
                                    <div>
                                        <Text strong>Có chính sách hủy phòng không?</Text>
                                        <Paragraph type="secondary" style={{ marginBottom: 0, marginTop: 4 }}>
                                            Có, bạn có thể hủy miễn phí trước 24h. Vui lòng xem chính sách chi tiết.
                                        </Paragraph>
                                    </div>
                                    <div>
                                        <Text strong>Homestay có gần trung tâm Hà Nội không?</Text>
                                        <Paragraph type="secondary" style={{ marginBottom: 0, marginTop: 4 }}>
                                            Các homestay tại Sóc Sơn cách trung tâm Hà Nội khoảng 30-40 phút di chuyển.
                                        </Paragraph>
                                    </div>
                                </Space>
                            </Card>
                        </Col>
                    </Row>
                </div>
            </Content>

            <AppFooter />
        </Layout>
    );
};

export default ContactPage;