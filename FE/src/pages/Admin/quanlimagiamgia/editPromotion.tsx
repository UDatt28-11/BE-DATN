import React, { useEffect, useState } from "react";
import { Form, Input, InputNumber, Select, DatePicker, Button, message, Row, Col, Spin, Space } from "antd";
import { useNavigate, useParams } from "react-router-dom";
import dayjs from "dayjs";
import { Promotion } from "../../../types/promotion/promotion";


const { Option } = Select;
const { RangePicker } = DatePicker;
const { TextArea } = Input;

const allLocations = ["Tất cả", "Hà Nội", "Đà Lạt", "Phú Quốc", "Nha Trang"];

// Mock dữ liệu (giống bên listPromotion)
const mockPromotions: Promotion[] = [
    {
        id: "1",
        code: "NEWYEAR2025",
        name: "Mừng Năm Mới 2025",
        description: "Giảm giá đặc biệt cho mùa lễ hội năm mới.",
        discountType: "Phần trăm",
        discountValue: 20,
        minOrderValue: 500000,
        maxDiscount: 200000,
        startDate: "2025-01-01",
        endDate: "2025-01-15",
        usageLimit: 200,
        usedCount: 50,
        status: "Đang hoạt động",
        applicableLocations: ["Tất cả"],
        createdAt: "2024-12-15",
        updatedAt: "2025-01-01",
    },
    {
        id: "2",
        code: "SUMMER2025",
        name: "Khuyến mãi mùa hè",
        description: "Giảm 100.000đ cho đơn hàng từ 1 triệu.",
        discountType: "Số tiền cố định",
        discountValue: 100000,
        minOrderValue: 1000000,
        startDate: "2025-06-01",
        endDate: "2025-08-31",
        usageLimit: 100,
        usedCount: 10,
        status: "Chưa áp dụng",
        applicableLocations: ["Phú Quốc", "Nha Trang"],
        createdAt: "2025-05-01",
        updatedAt: "2025-05-01",
    },
];

const EditPromotion: React.FC = () => {
    const { id } = useParams();
    const [form] = Form.useForm();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [promotion, setPromotion] = useState<Promotion | null>(null);

    // Giả lập tải dữ liệu từ API
    useEffect(() => {
        setLoading(true);
        setTimeout(() => {
            const found = mockPromotions.find((p) => p.id === id);
            if (found) {
                setPromotion(found);
                form.setFieldsValue({
                    ...found,
                    dateRange: [dayjs(found.startDate), dayjs(found.endDate)],
                });
            } else {
                message.error("Không tìm thấy mã giảm giá!");
                navigate("/quanlimagiamgia");
            }
            setLoading(false);
        }, 500);
    }, [id]);

    const handleUpdate = (values: any) => {
        const [startDate, endDate] = values.dateRange;
        const updatedPromotion: Promotion = {
            ...(promotion as Promotion),
            ...values,
            startDate: startDate.format("YYYY-MM-DD"),
            endDate: endDate.format("YYYY-MM-DD"),
            updatedAt: dayjs().format("YYYY-MM-DD"),
        };
        console.log("✅ Promotion updated:", updatedPromotion);
        message.success("Cập nhật mã giảm giá thành công!");
        navigate("/quanlimagiamgia");
    };

    if (loading || !promotion) {
        return (
            <div style={{ display: "flex", justifyContent: "center", marginTop: 100 }}>
                <Spin tip="Đang tải dữ liệu..." />
            </div>
        );
    }

    return (
        <Form form={form} layout="vertical" onFinish={handleUpdate} style={{ padding: 24 }}>
            <Row gutter={16}>
                <Col span={12}>
                    <Form.Item name="code" label="Mã giảm giá" rules={[{ required: true }]}>
                        <Input disabled />
                    </Form.Item>
                </Col>
                <Col span={12}>
                    <Form.Item name="status" label="Trạng thái" rules={[{ required: true }]}>
                        <Select>
                            <Option value="Đang hoạt động">Đang hoạt động</Option>
                            <Option value="Chưa áp dụng">Chưa áp dụng</Option>
                            <Option value="Hết hạn">Hết hạn</Option>
                            <Option value="Vô hiệu hóa">Vô hiệu hóa</Option>
                        </Select>
                    </Form.Item>
                </Col>
            </Row>

            <Form.Item name="name" label="Tên chương trình" rules={[{ required: true }]}>
                <Input />
            </Form.Item>

            <Form.Item name="description" label="Mô tả" rules={[{ required: true }]}>
                <TextArea rows={3} />
            </Form.Item>

            <Row gutter={16}>
                <Col span={8}>
                    <Form.Item name="discountType" label="Loại giảm" rules={[{ required: true }]}>
                        <Select>
                            <Option value="Phần trăm">Phần trăm</Option>
                            <Option value="Số tiền cố định">Số tiền cố định</Option>
                        </Select>
                    </Form.Item>
                </Col>
                <Col span={8}>
                    <Form.Item name="discountValue" label="Giá trị giảm" rules={[{ required: true }]}>
                        <InputNumber min={0} style={{ width: "100%" }} />
                    </Form.Item>
                </Col>
                <Col span={8}>
                    <Form.Item name="maxDiscount" label="Giảm tối đa (₫)">
                        <InputNumber min={0} style={{ width: "100%" }} />
                    </Form.Item>
                </Col>
            </Row>

            <Row gutter={16}>
                <Col span={12}>
                    <Form.Item name="minOrderValue" label="Đơn hàng tối thiểu (₫)" rules={[{ required: true }]}>
                        <InputNumber min={0} style={{ width: "100%" }} />
                    </Form.Item>
                </Col>
                <Col span={12}>
                    <Form.Item name="usageLimit" label="Giới hạn sử dụng" rules={[{ required: true }]}>
                        <InputNumber min={1} style={{ width: "100%" }} />
                    </Form.Item>
                </Col>
            </Row>

            <Form.Item name="dateRange" label="Thời gian áp dụng" rules={[{ required: true }]}>
                <RangePicker style={{ width: "100%" }} format="DD/MM/YYYY" />
            </Form.Item>

            <Form.Item name="applicableLocations" label="Địa điểm áp dụng" rules={[{ required: true }]}>
                <Select mode="multiple" options={allLocations.map((loc) => ({ label: loc, value: loc }))} />
            </Form.Item>

            <Space>
                <Button type="primary" htmlType="submit">Cập nhật</Button>
                <Button onClick={() => navigate("/quanlimagiamgia")}>Hủy</Button>
            </Space>
        </Form>
    );
};

export default EditPromotion;
