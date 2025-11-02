import React from "react";
import { Form, Input, Button, DatePicker, InputNumber, Select, message, Space } from "antd";

const { RangePicker } = DatePicker;
const { Option } = Select;

const AddBooking: React.FC = () => {
    const [form] = Form.useForm();

    const handleAddBooking = (values: any) => {
        console.log("Add booking values:", values);
        message.success("Đã thêm đặt phòng mới!");
        form.resetFields();
    };

    return (
        <div style={{ padding: 24 }}>
            <h2>Thêm đặt phòng mới</h2>
            <Form form={form} layout="vertical" onFinish={handleAddBooking}>
                <Form.Item label="Tên khách hàng" name="customerName" rules={[{ required: true }]}>
                    <Input />
                </Form.Item>
                <Form.Item label="SĐT khách hàng" name="customerPhone" rules={[{ required: true }]}>
                    <Input />
                </Form.Item>
                <Form.Item label="Email khách hàng" name="customerEmail">
                    <Input />
                </Form.Item>
                <Form.Item label="Homestay" name="homestayName" rules={[{ required: true }]}>
                    <Select placeholder="Chọn homestay">
                        <Option value="Homestay Đà Lạt">Homestay Đà Lạt</Option>
                        <Option value="Villa Nha Trang">Villa Nha Trang</Option>
                    </Select>
                </Form.Item>
                <Form.Item label="Check-in & Check-out" name="dates" rules={[{ required: true }]}>
                    <RangePicker />
                </Form.Item>
                <Form.Item label="Số đêm" name="nights" rules={[{ required: true }]}>
                    <InputNumber min={1} />
                </Form.Item>
                <Form.Item label="Số khách" name="guests" rules={[{ required: true }]}>
                    <InputNumber min={1} />
                </Form.Item>
                <Form.Item label="Tổng tiền" name="totalPrice" rules={[{ required: true }]}>
                    <InputNumber min={0} />
                </Form.Item>
                <Form.Item label="Phương thức thanh toán" name="paymentMethod" rules={[{ required: true }]}>
                    <Select>
                        <Option value="Tiền mặt">Tiền mặt</Option>
                        <Option value="Chuyển khoản">Chuyển khoản</Option>
                        <Option value="Thẻ tín dụng">Thẻ tín dụng</Option>
                    </Select>
                </Form.Item>
                <Form.Item label="Ghi chú" name="notes">
                    <Input.TextArea rows={3} />
                </Form.Item>
                <Form.Item>
                    <Button type="primary" htmlType="submit">Thêm đặt phòng</Button>
                </Form.Item>
            </Form>
        </div>
    );
};

export default AddBooking;
