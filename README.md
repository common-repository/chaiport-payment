# PortOne Woocommerce Plugin

[![forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]

<!-- PROJECT LOGO -->
<br />
<p align="center">
  <a href="#">
    <img src="./images/pay.png" alt="Logo">
  </a>

  <h3 align="center">PORTONE WOOCOMMERCE PLUGIN</h3>

  <p align="center">
    <a href="https://drive.google.com/file/d/1Uf1M_Lev7mRSH3BGkNzK0M_BBnX9b8Wu/view?usp=sharing">View Demo</a>
    ·
    <a href="https://github.com/iamport-intl/chaiport-woocommerce-plugin/issues">Report Bug</a>
    ·
    <a href="https://github.com/iamport-intl/chaiport-woocommerce-plugin/issues">Request Feature</a>
  </p>
</p>

<!-- TABLE OF CONTENTS -->
<details open="open">
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#contact">Contact</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

<br>
This is a WooCommerce plugin for making payments using PortOne. It adds a new payment option for your store on the checkout page and opens up a whole lot of options for the user to pay. 
The plugin can handle the payments and also your order statuses. It automatically updates the order status for the user if the transaction was successful or failed. It adds necessary notes to the order like transaction reference & if the transaction was unsuccessful, reason and status-code notes are attached to the order.<br/>

<!-- GETTING STARTED -->
## Getting Started

### Prerequisites

  ```
  1. WooCommerce installed in your wordpress 
  2. A verified Merchant Account with PortOne 
  3. Please set the Permalink to "Post Name" in WP Dashboard->Settings->Permalinks, the plugin webhooks and redirects won't work if the permalink is not set as required.
  ```

### Installation

1. Get your API Keys at [Merchant Portal][merchant-portal]
2. Download the plugin from [here](https://wordpress.org/plugins/chaiport-payment/)
3. Go to Plugins page and click on <b>Add New</b>, Upload the zip file and install
4. After installing you need to activate PortOne plugin from plugins page in your WordPress dashboard
5. Go to <b>Setting</b> in WooCommerce page of your dashboard and click on <b>Payments</b> tab
6. Enable the PortOne Plugin and then click on <b>Manage</b>
7. You need to check the <b>Enable PortOne Gateway</b> 
8. Enter the keys from step 1 and click on save
    ```
    Publishable Key ---> PortOne Key 
    Private Key     ---> PortOne Secure Secret Key
    ```
9. You'll have to add the webhook URL given in the settings to the Merchant Portal Webhooks section
10. You've successfully integrated PortOne Plugin on your store.


<!-- CONTRIBUTING -->
## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

See the [open issues][issues-url] for a list of proposed features (and known issues).

<!-- CONTACT -->
## Contact

PortOne Support - in.dev@chai.finance

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[forks-shield]: https://img.icons8.com/ios/50/000000/big-fork.png
[forks-url]: https://github.com/iamport-intl/chaiport-woocommerce-plugin/network/members
[stars-shield]: https://img.icons8.com/ios-filled/50/000000/star.png
[stars-url]: https://github.com/iamport-intl/chaiport-woocommerce-plugin/stargazers
[issues-shield]: https://img.icons8.com/material/50/000000/error--v1.png
[issues-url]: https://github.com/iamport-intl/chaiport-woocommerce-plugin/issues
[merchant-portal]: https://admin.portone.cloud/integration/api-general