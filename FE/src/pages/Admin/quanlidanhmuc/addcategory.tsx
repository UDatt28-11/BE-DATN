import React, { useState } from "react";
import { Modal, Form, Input, Upload, Checkbox, Row, Col, Space, Button } from "antd";
import { PictureOutlined } from "@ant-design/icons";
import type { UploadFile } from "antd/es/upload/interface";
import { Amenity } from "../../../types/category/category";


interface AddCategoryProps {
    visible: boolean;
    onCancel: () => void;
    onAdd: (values: any, fileList: UploadFile[], selectedAmenities: number[]) => void;
    amenities: Amenity[];
}

const AddCategory: React.FC<AddCategoryProps> = ({ visible, onCancel, onAdd, amenities }) => {
    const [form] = Form.useForm();
    const [fileList, setFileList] = useState<UploadFile[]>([]);
    const [selectedAmenities, setSelectedAmenities] = useState<number[]>([]);

    const handleOk = () => {
        form.validateFields().then((values) => {
            onAdd(values, fileList, selectedAmenities);
            form.resetFields();
            setFileList([]);
            setSelectedAmenities([]);
        });
    };

    return (
        <Modal
            title="Thêm danh mục mới"
            open={visible}
            onOk={handleOk}
            onCancel={() => {
                onCancel();
                form.resetFields();
            }}
            okText="Thêm mới"
            width={700}
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="name"
                    label="Tên danh mục"
                    rules={[{ required: true, message: "Vui lòng nhập tên danh mục!" }]}
                >
                    <Input placeholder="VD: Nhà gỗ, Villa, Căn hộ..." size="large" />
                </Form.Item>

                <Form.Item
                    name="description"
                    label="Mô tả"
                    rules={[{ required: true, message: "Vui lòng nhập mô tả!" }]}
                >
                    <Input.TextArea rows={4} placeholder="Nhập mô tả chi tiết..." />
                </Form.Item>

                <Form.Item label="Hình ảnh">
                    <Upload
                        listType="picture-card"
                        fileList={fileList}
                        onChange={({ fileList }) => setFileList(fileList)}
                        maxCount={1}
                        beforeUpload={() => false}
                    >
                        {fileList.length === 0 && (
                            <div>
                                <PictureOutlined />
                                <div style={{ marginTop: 8 }}>Upload</div>
                            </div>
                        )}
                    </Upload>
                </Form.Item>

                <Form.Item label="Tiện ích liên quan">
                    <Checkbox.Group
                        value={selectedAmenities}
                        onChange={(values) => setSelectedAmenities(values as number[])}
                        style={{ width: "100%" }}
                    >
                        <Row gutter={[16, 16]}>
                            {amenities.map((amenity) => (
                                <Col span={12} key={amenity.id}>
                                    <Checkbox value={amenity.id}>
                                        <Space>
                                            <span style={{ fontSize: 18 }}>{amenity.icon}</span>
                                            {amenity.name}
                                        </Space>
                                    </Checkbox>
                                </Col>
                            ))}
                        </Row>
                    </Checkbox.Group>
                </Form.Item>
            </Form>
        </Modal>
    );
};

export default AddCategory;
