# System Architecture: Smart Lost & Found Matching System

This diagram illustrates the comprehensive flow of the Smart Lost & Found Matching System, from user uploads to backend processing, matching logic, admin verification, and database storage.

```mermaid
%%{init: {'theme':'base', 'themeVariables': { 'primaryColor': '#ffffff', 'primaryBorderColor': '#333333', 'lineColor': '#666666', 'fontFamily': 'arial'}}}%%
flowchart TD
    %% Define Styles for clean white background and modern UI look
    classDef userAction fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px,color:#0d47a1,rx:10,ry:10;
    classDef processing fill:#fff3e0,stroke:#fb8c00,stroke-width:2px,color:#e65100,rx:5,ry:5;
    classDef logic fill:#f3e5f5,stroke:#8e24aa,stroke-width:2px,color:#4a148c,rx:5,ry:5;
    classDef decision fill:#e8f5e9,stroke:#43a047,stroke-width:2px,color:#1b5e20;
    classDef admin fill:#ffebee,stroke:#e53935,stroke-width:2px,color:#b71c1c,rx:5,ry:5;
    classDef database fill:#eceff1,stroke:#546e7a,stroke-width:2px,color:#263238;

    %% User Input Nodes
    U1["User Uploads Lost Item\n(Image, Title, Description, Category, Location)"] ::: userAction
    U2["User Uploads Found Item\n(Image, Title, Description, Category, Location)"] ::: userAction

    %% Database
    subgraph DB [Database]
        direction TB
        LIT[("Lost Items Table")] ::: database
        FIT[("Found Items Table")] ::: database
        MRT[("Match Results Table")] ::: database
    end

    %% Backend Processing Module
    subgraph BP [Backend Processing Module]
        direction TB
        ISE["Image Similarity Engine\n(CNN / OpenCV)"] ::: processing
        TSE["Text Similarity Engine\n(NLP, TF-IDF, Cosine Similarity)"] ::: processing
        MSC["Matching Score Calculator"] ::: processing
    end

    %% Flow from User to BP
    U1 --> ISE
    U1 --> TSE
    U2 --> ISE
    U2 --> TSE

    %% Direct DB Inserts from User Uploads
    U1 -.-> LIT
    U2 -.-> FIT

    %% Flow from Engines to Calculator
    ISE --"Image Score"--> MSC
    TSE --"Text Score"--> MSC

    %% Matching Logic
    subgraph ML [Matching Logic]
        direction TB
        Combine["Combine Scores"] ::: logic
        Formula["Final Score = (Text Score × 0.5) + (Image Score × 0.5)"] ::: logic
        Combine --> Formula
    end

    %% Calculator to Logic
    MSC --> Combine

    %% Decision Node
    Decision{"Final Score\n≥ 80%?"} ::: decision
    Formula --> Decision

    %% Admin Panel
    subgraph AP [Admin Panel - Pending Verification]
        direction TB
        AV["View Lost & Found Side-by-Side"] ::: admin
        APerc["View Match Percentage"] ::: admin
        AAction{"Approve or Reject Match"} ::: decision
        AV --> APerc --> AAction
    end

    %% Decision Branches
    Decision --"Yes (≥ 80%)"--> AV
    Decision --"No (< 80%)\nNo Match"--> MRT

    %% Admin Actions
    AAction --"Approve"--> MRT
    AAction --"Reject"--> MRT

```
