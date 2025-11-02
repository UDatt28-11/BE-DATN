// src/pages/quanlimagiamgia/addPromotion.tsx
import React from "react";
import { Form, Input, InputNumber, Select, DatePicker, Button, message, Row, Col, Space } from "antd";
import { useNavigate } from "react-router-dom";
import dayjs from "dayjs";
import { Promotion } from "../../../types/promotion/promotion";


const { Option } = Select;
const { RangePicker } = DatePicker;
const { TextArea } = Input;

const allLocations = ["Tất cả", "Hà Nội", "Đà Lạt", "Phú Quốc", "Nha Trang"];

const AddPromotion: React.FC = () => {
    const [form] = Form.useForm();
    const navigate = useNavigate();

    const handleSave = (values: any) => {
        const [startDate, endDate] = values.dateRange;
        const newPromotion: Promotion = {
            id: String(Date.now()),
            code: values.code.toUpperCase(),
            name: values.name,
            description: values.description,
            discountType: values.discountType,
            discountValue: values.discountValue,
            minOrderValue: values.minOrderValue,
            maxDiscount: values.maxDiscount,
            startDate: startDate.format("YYYY-MM-DD"),
            endDate: endDate.format("YYYY-MM-DD"),
            usageLimit: values.usageLimit,
            usedCount: 0,
            status: values.status,
            applicableLocations: values.applicableLocations,
            createdAt: dayjs().format("YYYY-MM-DD"),
            updatedAt: dayjs().format("YYYY-MM-DD"),
        };
        console.log("✅ Promotion created:", newPromotion);
        message.success("Thêm mã giảm giá thành công!");
        navigate("/quanlimagiamgia");
    };

    return (
        <Form form={form} layout="vertical" onFinish={handleSave} style={{ padding: 24 }}>
            <Row gutter={16}>
                <Col span={12}>
                    <Form.Item
                        name="code"
                        label="Mã giảm giá"
                        rules={[{ required: true, message: "Nhập mã" }]}
                    >
                        <Input maxLength={20} style={{ textTransform: "uppercase" }} />
                    </Form.Item>
                </Col>
                <Col span={12}>
                    <Form.Item name="status" label="Trạng thái" initialValue="Đang hoạt động">
                        <Select>
                            <Option value="Đang hoạt động">Đang hoạt động</Option>
                            <Option value="Chưa áp dụng">Chưa áp dụng</Option>
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
                    <Form.Item name="discountType" label="Loại giảm" initialValue="Phần trăm">
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
                <Button type="primary" htmlType="submit">Lưu</Button>
                <Button onClick={() => navigate("/quanlimagiamgia")}>Hủy</Button>
            </Space>
        </Form>
    );
};

export default AddPromotion;
